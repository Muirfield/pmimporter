<?php
namespace pmimporter;
use pmimporter\LevelFormat;
use pmsrc\utils\Binary;


class RegionLoader {
	const COMPRESSION_GZIP = 1;
	const COMPRESSION_ZLIB = 2;
	const MAX_SECTOR_LENGTH = 256 << 12; //256 sectors, (1 MB)
	public static $COMPRESSION_LEVEL = 9;

	protected $x;
	protected $z;

	protected $filePath;
	protected $filePointer;
	protected $lastSector;
	protected $formatProvider;
	protected $locationTable = [];
	protected $dirty;

	protected static function getChunkOffset($x, $z){
		return $x + ($z << 5);
	}

	public function __construct($path,$rX,$rZ,$ext) {
		$this->dirty = false;
		$this->x = $rX;
		$this->z = $rZ;
		//echo __METHOD__.",".__LINE__."\n";//##DEBUG
		//echo "rX=$rX rZ=$rZ\n";//##DEBUG
		$this->filePath = $path."/region/r.$rX.$rZ.$ext";
		if (file_exists($this->filePath)) {
			$this->filePointer = fopen($this->filePath,"rb");
			stream_set_read_buffer($this->filePointer,1024*16); // 16KB
			$this->loadLocationTable();
			//echo __METHOD__.",".__LINE__."\n";//##DEBUG
		} else {
			$this->filePointer = fopen($this->filePath,"w+b");
			stream_set_write_buffer($this->filePointer,1024*16); // 16KB
			stream_set_read_buffer($this->filePointer,1024*16); // 16KB
			$this->createBlank();
			//echo __METHOD__.",".__LINE__."\n";//##DEBUG
		}
	}
	public function __destruct() {
		if(is_resource($this->filePointer)) {
			if ($this->dirty) $this->writeLocationTable();
			fclose($this->filePointer);
		}
	}
	protected function isChunkPresent($index) {
		return !($this->locationTable[$index][0] === 0 or $this->locationTable[$index][1] === 0);
	}
	public function chunkExists($x,$z) {
		return $this->isChunkPresent(self::getChunkOffset($x,$z));
	}
	public function readChunk($x,$z) {
		//echo  __METHOD__.",".__LINE__."\n";//##DEBUG
		$index = self::getChunkOffset($x, $z);
		if($index < 0 or $index >= 4096) return null;
		if(!$this->isChunkPresent($index)) return null;
		fseek($this->filePointer, $this->locationTable[$index][0] << 12);
		$length = Binary::readInt(fread($this->filePointer, 4));
		$compression = ord(fgetc($this->filePointer));
		if($length <= 0 or $length > self::MAX_SECTOR_LENGTH) return null;
		//echo " READ($x,$z): ".$length." bytes\n";//##DEBUG
		return fread($this->filePointer,$length-1);
	}
	public function close(){
		if (!$this->dirty) $this->writeLocationTable();
		fclose($this->filePointer);
		$this->filePointer = null;
	}

	protected function createBlank(){
		fseek($this->filePointer, 0);
		ftruncate($this->filePointer, 0);
		$this->lastSector = 2;
		$table = "";
		for($i = 0; $i < 1024; ++$i){
			$this->locationTable[$i] = [0, 0];
			$table .= Binary::writeInt(0);
		}

		$time = time();
		for($i = 0; $i < 1024; ++$i){
			$this->locationTable[$i][2] = $time;
			$table .= Binary::writeInt($time);
		}

		fwrite($this->filePointer, $table, 4096 * 2);
	}

	protected function loadLocationTable(){
		fseek($this->filePointer, 0);
		$this->lastSector = 2;

		$table = fread($this->filePointer, 4 * 1024 * 2); //1024 records * 4 bytes * 2 times
		for($i = 0; $i < 1024; ++$i){
			$index = unpack("N", substr($table, $i << 2, 4))[1];
			$this->locationTable[$i] = [$index >> 8, $index & 0xff, unpack("N", substr($table, 4096 + ($i << 2), 4))[1]];
			if(($this->locationTable[$i][0] + $this->locationTable[$i][1]) > $this->lastSector){
				$this->lastSector = $this->locationTable[$i][0] + $this->locationTable[$i][1];
			}
		}
	}

	protected function reopenRW() {
		// Make sure filePointer is writeable...
		$meta = stream_get_meta_data($this->filePointer);
		if ($meta["mode"] != "rb") return;// Already R/W
		fclose($this->filePointer);
		$this->filePointer = fopen($this->filePath,"r+b");
		stream_set_write_buffer($this->filePointer,1024*16); // 16KB
		stream_set_read_buffer($this->filePointer,1024*16); // 16KB
	}

	protected function writeLocationTable(){
		$this->reopenRW();
		$write = [];

		for($i = 0; $i < 1024; ++$i){
			$write[] = (($this->locationTable[$i][0] << 8) | $this->locationTable[$i][1]);
		}
		for($i = 0; $i < 1024; ++$i){
			$write[] = $this->locationTable[$i][2];
		}
		fseek($this->filePointer, 0);
		fwrite($this->filePointer, pack("N*", ...$write), 4096 * 2);
		$this->dirty = false;
	}

	public function getX(){
		return $this->x;
	}

	public function getZ(){
		return $this->z;
	}

	public function writeChunk($oX,$oZ,$data) {

		$length = strlen($data) + 1;
		if($length + 4 > self::MAX_SECTOR_LENGTH){
			die(__CLASS__."::".__METHOD__.": Chunk is too big! ".($length + 4)." > ".self::MAX_SECTOR_LENGTH."\n");
		}
		$this->reopenRW();
		$sectors = (int) ceil(($length + 4) / 4096);
		$index = self::getChunkOffset($oX, $oZ);

		// We always append data...
		$this->locationTable[$index][0] = $this->lastSector;
		$this->locationTable[$index][1] = $sectors;
		$this->locationTable[$index][2] = time();
		$this->lastSector += $sectors;

		fseek($this->filePointer, $this->locationTable[$index][0] << 12);
		fwrite($this->filePointer, str_pad(Binary::writeInt($length) . chr(self::COMPRESSION_ZLIB) . $data, $sectors << 12, "\x00", STR_PAD_RIGHT));
		$this->dirty = true;	// Make sure the location table gets updated at the end...
	}

	public function addChunks(array &$chunks) {
		$rX = $this->getX() << 5;
		$rZ = $this->getZ() << 5;
		$c = 0;
		for ($x = 0; $x < 32; $x++) {
			for ($z = 0; $z < 32; $z++) {
				if (!$this->chunkExists($x,$z)) continue;
				$chunks[implode(",",[$rX+$x,$rZ+$z])] = [ $rX + $x, $rZ + $z ];
				++$c;
			}
		}
		return $c;
	}
}
