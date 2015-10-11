#!/bin/sh
grep 'const ' | sed -e 's/^\s*const\s*//' -e 's/;.*$//' -e's/\s*=\s*/	/';
