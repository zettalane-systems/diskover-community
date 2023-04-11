#
DATAPATH=${1:?Data path required}
PORT=${2:=9000}

echo "DATAPATH=$DATAPATH PORT=$PORT" >&2

mkdir -p $DATAPATH

if [ ! -d "${DATAPATH}" ] ; then
	echo "$DATAPATH is not a valid directory" >&2
	exit 1
fi

yum -q -y install git
mkdir /tmp/diskover
(cd /tmp/diskover ; \
	git clone -b releng/mayanas --single-branch https://github.com/zettalane-systems/diskover-community.git )
cd /tmp/diskover/diskover-community

for f in `grep -rl gtag diskover-web` ; do
	sed '/php.*sendanondata/,/<\?php.*>/{d}'  $f
done

yum -q -y install java-1.8.0-openjdk.x86_64
yum install -q -y https://artifacts.elastic.co/downloads/elasticsearch/elasticsearch-7.17.4-x86_64.rpm

sed -i 's!^path.data:.*!path.data: '$DATAPATH'!' /etc/elasticsearch/elasticsearch.yml
chown elasticsearch:elasticsearch $DATAPATH

mkdir -p /etc/systemd/system/elasticsearch.service.d
cat <<!CONF > /etc/systemd/system/elasticsearch.service.d/elasticsearch.conf
[Service]
LimitMEMLOCK=infinity
LimitNPROC=4096
LimitNOFILE=65536
!CONF

systemctl enable elasticsearch.service
systemctl start elasticsearch.service
systemctl status elasticsearch.service

curl http://localhost:9200/_cat/health?v

yum -q -y install nginx
systemctl enable nginx
systemctl start nginx
systemctl status nginx

yum -q -y install http://rpms.remirepo.net/enterprise/remi-release-7.rpm
yum -q -y install yum-utils
yum-config-manager -q --enable remi-php74
yum -q -y install php php-common php-fpm php-opcache php-cli php-gd php-mysqlnd php-ldap php-pecl-zip php-xml php-xmlrpc php-mbstring php-json php-sqlite3

sed -i  -e 's/^user.*apache/user = nginx/' \
	-e 's/group.*apache/group = nginx/' \
	-e 's!^listen[ ]*=.*!listen = /var/run/php-fpm/php-fpm.sock!' \
	-e 's/;*listen.owner[ ]*=.*/listen.owner = nginx/' \
	-e 's/;*listen.group[ ]*=.*/listen.group = nginx/' /etc/php-fpm.d/www.conf

chown -R root:nginx /var/lib/php
mkdir /var/run/php-fpm
chown -R nginx:nginx /var/run/php-fpm
systemctl enable php-fpm
systemctl start php-fpm
systemctl status php-fpm

rsync -a diskover-web /var/www
(cd /var/www/diskover-web/src/diskover; cp Constants.php.sample Constants.php)

chown -R nginx:nginx  /var/www/diskover-web
chcon -R -t httpd_sys_content_rw_t /var/www/diskover-web


cat - <<'CONF' > /etc/nginx/conf.d/diskover-web.conf
server {
        listen   9000;
        server_name  diskover-web;
        root   /var/www/diskover-web/public;
        index  index.php index.html index.htm;
        error_log  /var/log/nginx/error.log;
        access_log /var/log/nginx/access.log;
        location / {
            try_files $uri $uri/ /index.php?$args =404;
        }
        location ~ \.php(/|$) {
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            set $path_info $fastcgi_path_info;
            fastcgi_param PATH_INFO $path_info;
            try_files $fastcgi_script_name =404; 
            fastcgi_pass unix:/var/run/php-fpm/php-fpm.sock;
            #fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 900;
            fastcgi_buffers 16 16k;
            fastcgi_buffer_size 32k;
        }
}
CONF

if [ "$PORT" != "9000" ] ; then
	sed -i 's/9000;/'$PORT /etc/nginx/conf.d/diskover-web.conf
fi

systemctl restart nginx
setsebool httpd_can_network_connect on

firewall-cmd --add-port=$PORT/tcp --permanent
firewall-cmd --reload

rsync -a diskover /opt/
(cd /opt/diskover ; pip3 install -r requirements.txt )

mkdir -p ~/.config/diskover
cp /opt/diskover/configs_sample/diskover/config.yaml ~/.config/diskover

