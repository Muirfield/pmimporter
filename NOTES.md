
1. read input
2. get block arrays
   - str[y] = "bytes"
3. convert blocks
4. if order changes: *translate blocks*
    str-> getBlockIdArray
    str-> getBlockDataArray
    make orderIds
    Id Array -> str[y]

5. save blocks

- load chunk
- save chunk



Conversion using strtr or strreplace ... test performance
Format:

anvil - read/write
mcregion - read/write
leveldb - read/write
pmf - read
mcpe020 - read

Import into worlds ... can exist
specify chunks to do (ranges)
sort through chunks
threads arbitrate access.

Chunk
  - int xPos
  - int xPos
  - str[y]|str[] blocks
  - str[y]|str[] meta
  - str[y]|str[] blocklight
  - str[y]|str[] skylight
  - int[] biomeColors
  - int[] heightMap
  - nbt[] entities
  - nbt[] tiles
  - bool isGenerated
  - bool isPopulated
  - bool isLightPopulated

new Chunk - create empty chunk
fromBinary - load Chunk
static-CopyChunk
getXXX - always returns ZXY order
getRawXXX - can return in any order
getBlocks - getRawBlocks | setBlocks - setRawBlocks
getMeta - getRawMeta | setMeta - setRawMeta
getBlockLight - getRawBlockLight | setBlockLight - setRawBlockLight
getSkyLight - getRawSkyLight | setSkyLight - setRawSkyLight
getBiomeColors | setBiomeColors
getHeightMap | setHeightMap
getEntities |  setEntities
getTiles | setTiles
isGenerated | setGenerated(bool)
isPopulated | setPopulated(bool)
isLightPopulated | setLightPopulated

//- Anvil: shift when loading?
shiftBlocks(y) - shift blocks up/down
translateBlocks(data) - convert blocks

Chunk
  StandardChunk  
    AnvilChunk
  ReadOnly
    OldChunk
    PmfChunk


level_dat
- spawn(x,y,z)
- str name
- int seed
- str generatorName
- int generatorVersion
- str presets





interface Chunk {
  get/setX
  get/setZ
  is/setPopulated
  is/setGenerated
  get/setBiomeIdArray
  get/setBiomeColorArray
  get/setHeightMapArray


  get/setEntities
  get/setTiles






  populateSkyLight?
  recalculateHeightMap?




ORDER_YZX -> ORDER_ZXY
ORDER_ZXY -> ORDER_YZX


= Chunk File Format =

Chunks files store the terrain and entities within a 16x16x128 area. They also store precomputed lighting and heightmap data for Minecraft's performance.

The Data, SkyLight, and BlockLight are arrays of 4-bit values.  The low bits of the first byte of one of these corresponds to the first block in the Blocks array

== NBT Structure ==

* TAG_Compound('''"Level"'''): Chunk data.
** TAG_Byte_Array('''"Blocks"'''): 32768 bytes of [[Data values#Block IDs|block IDs]] defining the terrain. 8 bits per block. See [[#Block Format|Block Format]] below for byte ordering.
** TAG_Byte_Array('''"Data"'''): 16384 bytes of [[Data values#Data|block data]] additionally defining parts of the terrain. 4 bits per block.
** TAG_Byte_Array('''"SkyLight"'''): 16384 bytes recording the amount of sun or moonlight hitting each block. 4 bits per block. Makes day/night transitions smoother compared to recomputing per level change.
** TAG_Byte_Array('''"BlockLight"'''): 16384 bytes recording the amount of block-emitted light in each block. 4 bits per block. Makes load times faster compared to recomputing at load time.
** TAG_Byte_Array('''"HeightMap"'''): 256 bytes of heightmap data. 16 x 16. Each byte records the lowest level in each column where the light from the sky is at full strength. Speeds computing of the SkyLight. '''Note:''' This array's indexes are ordered Z,X whereas the other array indexes are ordered X,Z,Y.
** TAG_List('''"Entities"'''): Each TAG_Compound in this list defines an entity in the chunk. See [[#Entity Format|Entity Format]] below.
** TAG_List('''"TileEntities"'''): Each TAG_Compound in this list defines a tile entity in the chunk. See [[#Tile Entity Format|Tile Entity Format]] below.
** TAG_Long('''"LastUpdate"'''): Tick when the chunk was last saved.
** TAG_Int('''"xPos"'''): X position of the chunk. Should match the file name.
** TAG_Int('''"zPos"'''): Z position of the chunk. Should match the file name.
** TAG_Byte('''"TerrainPopulated"'''): 1 or 0 (true/false) indicate whether the terrain in this chunk was populated with special things. (Ores, special blocks, trees, dungeons, flowers, waterfalls, etc.)

== Block Format ==

Blocks are laid out in sets of vertical columns, with the rows going east-west through chunk, and columns going north-south. Blocks in each chunk are accessed via the following method:

unsigned char BlockID = Blocks[ y + ( z * ChunkSizeY(=128) + ( x * ChunkSizeY(=128) * ChunkSizeZ(=16) ) ) ];

The coordinate system is as follows:
* '''X''' increases South, decreases North
* '''Y''' increases upwards, decreases downwards
* '''Z''' increases West, decreases East

The Data, BlockLight, and SkyLight arrays have four bits for each byte of the Blocks array.  The least significant bits of the first byte of the Data, BlockLight, or SkyLight arrays correspond to the first byte of the Blocks array.
