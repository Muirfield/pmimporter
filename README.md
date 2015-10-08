<img src="https://raw.githubusercontent.com/alejandroliu/pmimporter/master/ImportMap-icon.png" style="width:64px;height:64px" width="64" height="64"/>

# pmimporter

* Summary: Import world maps into PocketMine-MP
* WebSite: [github](https://github.com/alejandroliu/pmimporter)

## Overview

* nbtdump - Dump the contents of NBT files
* level - manipulate some level.dat settings
* check - read world maps and analyze the block, object composition
* convert - main conversion tool

## Description

A collection of tools used for importing world maps for use with
PocketMine-MP.

It supports the following formats:

- McRegion: Minecraft PC Edition pre1.2
- Anvil: Minecraft PC Edition v1.2 and better
- PMF: PocketMine v1.3.  **READ-ONLY** format.
- mcpe0.2.0: Minecraft PE finite worlds.   **READ-ONLY** format.
- **INDEV** LevelDB: Minecraft PE v0.9.0 (or better) infinite worlds.

When importing maps it will by default check the used blocks to make sure
that only blocks supported by Minecraft PE are generated.  It does this by
either mapping these blocks or removing them.  This conversion/fitering can
be disabled or tweaked with an user provided `rules` file. Similarly, Tiles
and Entities that are not supported by Minecraft PE are eliminated. This is
done because using these on a Minecraft PE client that does not support them
would cause the game to crash.

## Command Usage

In general, the command usage is:

* _path-to-php-executable_ _path-to-pmimporter.phar_ _sub-command_ [options]

### Sub-commands

#### convert

This is the main map importing command.

	convert [options] srcpath dstpath

The _srcpath_ is a path to a folder tha contains a Minecraft/PocketMine
world.

The _dstpath_ is a path to the destination Minecraft/PocketMine world.  If
_dstpath_ does not exist a new world will be created.  If it exists, chunks
will be imported into the existing world.

Options:

* --format=_format_
  - sets the output format if the destination is a new map.  Defaults to
	  **mcregion**.  Possible options: **anvil**, **mcregion**, **LevelDB**.
* --threads=_cores_
  - Will spawn _cores_ threads to process chunks.
* --yoff=_offset_
  - Offset blocks by the given _offset_ number of blocks.  If the number
	  is negative, the world will be shifted **down**.  If the number is
		positive, the world will be shifted **up**.
* --adjchunk=_x,z_
  - When writing chunks, these will be shifted by _x_ and/or _z_ chunks.
* --rules=_file_
  - Read additional translation rules from _file_.
* --x=_n_, --min-x=_min_, --max-x=_max_
  - Will limit the imported chunk region by the specified _min_ and _max_
	  **x** values (inclusive).  The **--x** option is a shortcut for
		specifying **--min-x** and **--max-x** both equal to _n_.
	- The default is to process all chunks
	- NOTE: Limits are applied **before** the **adjchunk** shifts are
	  calculated.
* --z=_n_, --min-z=_min_, --max-z=_max_
  - Will limit the imported chunk region by the specified _min_ and _max_
	  **z** values (inclusive).  The **--z** option is a shortcut for
		specifying **--min-z** and **--max-z** both equal to _n_.
	- The default is to process all chunks
	- NOTE: Limits are applied **before** the **adjchunk** shifts are
	  calculated.
* --convert, --no-convert
  - enable or disable block conversions/filtering.  **--convert** is the
		default.
* --clobber, --no-clobber
  - If importing chunks to an existing world, if **--clobber** is specified,
	  existing chunks will be overwritten.  The default is **--no-clobber** so
		existing chunks will be skipped.

#### check

Analyze the number of chunks, blocks, etc in a world map.

	check [options] worldpath

_worldpath_ is a folder containing the world to analyze.

Options:

* --check-chunks, --no-check-chunks
  - Will compute the block/entity make-up of selected chunks.  The default
	  is **--no-check-chunks**.
* --x=_n_, --min-x=_min_, --max-x=_max_
  - Will limit the processed chunk region by the specified _min_ and _max_
	  **x** values (inclusive).  The **--x** option is a shortcut for
		specifying **--min-x** and **--max-x** both equal to _n_.
	- The default is to process all chunks
	- NOTE: Limits are applied **before** the **adjchunk** shifts are
	  calculated.
* --z=_n_, --min-z=_min_, --max-z=_max_
  - Will limit the processed chunk region by the specified _min_ and _max_
	  **z** values (inclusive).  The **--z** option is a shortcut for
		specifying **--min-z** and **--max-z** both equal to _n_.
	- The default is to process all chunks
	- NOTE: Limits are applied **before** the **adjchunk** shifts are
	  calculated.

#### level

Displays and modifies certain level attributes.

	level [options] worldpath

Will display (and modify) attributes for the specified world.

Options:

* --spawn=_x,y,z_
  - Sets the world spawn point.
* --name=_text_
 	- Sets the Level name.
* --seed=_integer_
	- Sets the random Seed.
* --generator=_name_
  - Sets the terrain generator.  PocketMine by default only supports
		**flat** and **normal**.
* --preset=_txt_
	- Sets the Terrain generator **preset** string.
  	Ignored by the **normal** generator.  Used by **flat**.
* --fixname
  - Will set the Level name string to the _worldpath_ folder's name.

#### nbtdump

Dumps the contents of an `NBT` formatted file.  Usually the `level.dat`
file in the world folder.

	nbtdump nbt_file

## Installation

Requirements:

* This software is only supported on Linux
* PHP v5.6.xx, version used by PocketMine-MP.  This one contains
  most dependancies.  Note that depending on the PHP binary being used
	the **LevelDB** format may or may **not** be supported.

Download `pmimporter.phar` and use.  It does **not** need to be
installed.


## Configure translation

You can configure the translation by providing a `rules` file and
passing it to `covert` with the `--rules` option. The format of `rules.txt`
is as follows:

* comments, start with `;` or `#`.
<!-- * `BLOCKS` - indicates the start of a blocks translation rules section. -->
* `source-block = target-block` is a translation rule.  Any block of
  type `source-block` is converted to `target-block`.

There is a default set of conversion rules, but you can tweak it by
using `--rules`.

## FAQ

* Q: Why it takes so long?
* A: Because my programming skills suck.
* Q: Does it support LevelDB files (Pocket Edition v0.9.0 infinite
  worlds)?
* A: There is experimental support for that.  Your **PHP** installation
	needs to have a compatible [leveldb](https://github.com/PocketMine/php-leveldb).
* Q: Why tall builds seem to be chopped off at te top?
* A: That is a limitation of Pocket Edition.  It only supports chunks
  that are up to 128 blocks high, while the PC edition Anvil worlds
  can support up to 256 blocks high.  You can shift worlds down by
  using the `--yoff` option.  So if you use `--yoff=-40` that will move the
  build down 40 blocks.  **BE CAREFUL NOT TO REMOVE THE GROUND**
* Q: Why I see some blocks that are not in the original map?
* A: These have to do with how the translation happens.  There are
  blocks that are not supported by Minecraft Pocket Edition.  These
  need to be map to a block supported by MCPE.  You can tweak this by
  modifying the conversion rules.

## References

* [PocketMine-MP](http://www.pocketmine.net/)
* [Block defintions](https://raw.githubusercontent.com/alejandroliu/pmimporter/master/classlib/pmimporter/blocks.txt)
* [Minecraft PC data values](http://minecraft.gamepedia.com/Data_values)
* [Minecraft PE data values](http://minecraft.gamepedia.com/Data_values_%28Pocket_Edition%29)

## Issues and Bugs

* PMF1.3 performance is still low and also Entities from this format are
  **not** imported.  Note that since there are very few PMF1.3 maps around,
	this is something that will probably **not** be fixed.
* When converting Anvil to non-Anvil formats, maps are silently truncated to
	be less than 128 blocks high, unless.  Currently Anvil is the only map
	format that support 256 blocks high worlds.

# Todo

* Finish LevelDB
  - reading
	- writing
* Copy Entities
  - reading: anvil, mcregion, mcpe020, leveldb
	- writing: anvil, mcregion, level
* Check Entities
* Copy Tiles
	- reading: anvil, mcregion, mcpe020, pmf, leveldb
	- writing: anvil, mcregion, leveld
* Check Tiles
* Re-write documentation
* Testing
  - conversion
	- features

| reading\writing | anvil | mcregion | LevelDB |
|-----------------|-------|----------|---------|
| mcregion        |       |          |         |
| anvil           |       |          |         |
| LevelDB         |       |          |         |
| McPe020         |       |          |         |
| PMF1.3          |       |          |         |

## Changes

* 2.0:
  - removed commands: entities, dumpchunk
  - syntax of sub-commands changed
  - PocketMine-MP plugin has been discontinued
  - Major speed improvements
  - Imports chunks into existing maps.
	- **TODO**: Initial LevelDB support
* 1.5upd2: Update
  - Added new blocks since 0.10
* 1.5upd1: Bugfix
  * Minor fix in plugin code
* 1.5: Bugfix
  * BugFixes in MCPE0.2.0 format.
  * BugFixes with region offsests on negative values
  * Tweaked builds and subcommand names
* 1.4: Maintenance release
  * pmentities fix typos
  * minor text info tweaks
  * Added pmentity to dump entity data
  * Added region settings to MCPE0.2.0 and PMF1.3 formats.
  * Fixed offset functionality.
  * Filter out Dropped Item entities.
* 1.3: OldSkool fixes
  * Added support for Tiles to PMF maps.
  * Added support for Tiles and Entities fo MCPE 0.2.0 maps.
  * Fixed HeightMap calculations in PMF and MCPE 0.2.0 formats
  * Added `settings` capability to tweak conversion.
  * Merged ImportMap and pmimporter into a single Phar file.
* 1.2: Fixes
  * pmcheck: show height map statistics.
  * pmconvert: offset y coordinates
* 1.1: OldSkool release
  * Added support for maps from Minecraft Pocket Edition 0.2.0 - 0.8.1
  * Added support for PMF maps from PocketMine v1.3.
* 1.0 : First release

## Copyright


Some of the code used in this program come from PocketMine-MP,
licensed under GPL.

    pmimporter  
    Copyright (C) 2015 Alejandro Liu  
    All Rights Reserved.

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
