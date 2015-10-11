<?php
namespace pmimporter;

abstract class Misc {
	public static function from_camel_case($input) {
		preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
		$ret = $matches[0];
		foreach ($ret as &$match) {
			$match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
		}
		return implode('_', $ret);
	}
	public static function readTable($fn,$cols=2) {
		$table = [];
		$fp = fopen($fn,"r");
		if (!$fp) return false;
		while (($ln = fgets($fp)) !== false) {
			if (preg_match('/^\s*[#;]/',$ln)) continue; // Skip comments
			$ln = preg_replace('/^\s+/','',$ln);
			$ln = preg_replace('/\s+$/','',$ln);
			if ($ln == '') continue;	// Skip empty lines
			$ln = preg_split('/\s+/',$ln,$cols+1);
			if ($cols < 2) continue;
			$table[] = $ln;
		}
		fclose($fp);
		if (!count($table)) return false;
		return $table;
	}
}
