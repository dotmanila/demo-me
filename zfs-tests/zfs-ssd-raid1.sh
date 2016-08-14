#!/bin/bash

set -e

zfs unmount -a
zpool destroy ssdr1

rm -rf /ssdr1
rm -rf /ssdr1/mysql
rm -rf /ssdr1/temp1
rm -rf /ssdr1/temp2

zpool create -f ssdr1 mirror \
/dev/sdf \
/dev/sdg

zfs set atime=off               ssdr1
zfs set compression=lz4         ssdr1
zfs set dedup=off               ssdr1
zfs set primarycache=metadata   ssdr1
zfs set relatime=off            ssdr1
zfs set secondarycache=metadata ssdr1
zfs set recordsize=16k          ssdr1

zfs create -o mountpoint=/ssdr1/mysql ssdr1/mysql
zfs create -o primarycache=all -o sync=disabled -o recordsize=8k -o mountpoint=/ssdr1/temp1 ssdr1/temp1
zfs create -o mountpoint=/ssdr1/temp2 ssdr1/temp2