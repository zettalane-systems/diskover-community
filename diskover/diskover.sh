#!/bin/bash

JOBID=${1:?"No jobid was specified"}
SCAN_URL=${2:?"No scan url specified"}
RUNAS=$3

if [ "$RUNAS" = "root" ] ; then
	RUNAS=""
fi

proto=$(echo $SCAN_URL | cut -f1 -d:)

case $proto in
nfs)
	SERVER_SHARE=`echo $SCAN_URL | sed -e 's!nfs://!!'`
	SERVER=$(echo $SHARE| cut -f1 -d:)
	SHARE=$(echo $SHARE| cut -f2 -d:)
	MNTPATH=/mnt/$SERVER/diskover-$JOBID
	
	[ ! -d "$MNTPATH" ] && mkdir -p $MNTPATH
	mount -t nfs -o ro,noatime,nodiratime $SERVER_SHARE $MNTPATH || 
		exit 1
	is_mounted=1
	;;

cifs|smb)
	SERVER_SHARE=`echo $SCAN_URL | sed -e 's!smb://!!'`
	SERVER=$(echo $SHARE| cut -f1 -d:)
	SHARE=$(echo $SHARE| cut -f2 -d:)
	MNTPATH=/mnt/$SERVER/diskover-$JOBID
	
	[ ! -d "$MNTPATH" ] && mkdir -p $MNTPATH
	if [ ! -z "$RUNAS" ] ; then
		username=$RUNAS
	fi
	mount -t cifs -o username=$username //$SERVER/SHARE $MNTPATH || 
		exit 2
	is_mounted=1
	;;
file)
	MNTPATH=`echo $SCAN_URL | sed -e 's!file://!!'`
	
	if [ ! -d "$MNTPATH" ] ; then
		echo "Not a valid directory $MNTPATH for diskover scan" &>2
		exit 3
	fi
	if [ ! -z "$RUNAS" ] ; then
		username=$RUNAS
	fi
	;;
*)
	echo "unknow protocol sharing"
	exit 10
esac

if [ ! -z "$RUNAS" ] ; then
	RUNAS="-u $RUNAS"
fi

sudo $RUNAS python3 /opt/diskover/diskover.py --jobid $JOBID \
	$MNTPATH &>> /var/log/diskover/diskover-$JOBID.log

if [ "$is_mounted" = "1" ] ; then
	umount $MNTPATH
fi

