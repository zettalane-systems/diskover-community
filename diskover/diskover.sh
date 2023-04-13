#!/bin/bash

JOBID=${1:?"No jobid was specified"}
SCAN_URL=${2:?"No scan url specified"}
RUNAS=$3
CIFS_CREDS="$4"

if [ "$RUNAS" = "root" ] ; then
	RUNAS=""
fi

proto=$(echo $SCAN_URL | cut -f1 -d:)

case $proto in
nfs)
	SERVER_SHARE=`echo $SCAN_URL | sed -e 's!nfs://!!'`
	SERVER=$(echo $SERVER_SHARE| cut -f1 -d:)
	SHARE=$(echo $SERVER_SHARE| cut -f2 -d:)
	MNTPATH=/mnt/$SERVER/diskover-$JOBID
	
	[ ! -d "$MNTPATH" ] && mkdir -p $MNTPATH
	mount -t nfs -o ro,noatime,nodiratime $SERVER_SHARE $MNTPATH || 
		exit 1
	is_mounted=1
	;;

cifs|smb)
	SERVER_SHARE=`echo $SCAN_URL | sed -e 's!smb://!!'`
	SERVER=$(echo $SERVER_SHARE| cut -f1 -d:)
	SHARE=$(echo $SERVER_SHARE| cut -f2 -d:)
	MNTPATH=/mnt/$SERVER/diskover-$JOBID

	CIFS_ARGS="ro"

	if [ -z "$CIFS_CREDS" ] ; then
		echo "warning: mount.cifs requires username/password"
	else
		user=$(echo $CIFS_CREDS | cut -f1 -d:)
		pass=$(echo $CIFS_CREDS | cut -f2 -d:)
		CIFS_ARGS="$CIFS_ARGS,username=$user,password=$pass"
	fi
	
	[ ! -d "$MNTPATH" ] && mkdir -p $MNTPATH
	if [ ! -z "$RUNAS" ] ; then
		username=$RUNAS
	fi
	mount -t cifs -o $CIFS_ARGS //$SERVER/$SHARE $MNTPATH ||
		exit 1
	is_mounted=1
	;;
file)
	MNTPATH=`echo $SCAN_URL | sed -e 's!file://!!'`
	
	if [ ! -d "$MNTPATH" ] ; then
		echo "Not a valid directory $MNTPATH for diskover scan" &>2
	fi
	if [ ! -z "$RUNAS" ] ; then
		username=$RUNAS
	fi
	;;
*)
	echo "unknown protocol sharing"
	exit 10
esac

if [ ! -z "$RUNAS" ] ; then
	RUNAS="-u $RUNAS"
fi

[ ! -d /var/log/diskover ] && mkdir -p /var/log/diskover

sudo $RUNAS python3 /opt/diskover/diskover.py --jobid $JOBID \
	$MNTPATH &>> /var/log/diskover/diskover-$JOBID.log&
DISKOVERPID=$!

retry=3
while [ $retry -gt 0 ] ; do
	psinfo=$(ps --ppid $DISKOVERPID -h -o pid)
	if [ ! -z "$psinfo" ] ; then
		echo "Diskover scan PID $psinfo"
		exit 0
	fi
	sleep 1
	retry=$((retry -1))
done

echo "Unable to find Diskover scan PID!"
if [ "$is_mounted" = "1" ] ; then
	umount $MNTPATH
fi

