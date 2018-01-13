XPCoin Blockfile Walker
===========


## Install

Depends: PHP5.6, db4

```
sudo apt install php5.6-dev php5.6-fpm php5.6-bcmath

git clone https://github.com/nishimura/xpcoin-blockfile-walker.git
wget http://download.oracle.com/berkeley-db/db-6.2.32.tar.gz

tar xvzf db-6.2.32.tar.gz
cd db-6.2.32/build_unix/
../dist/configure --prefix=/opt/db-6.2.32 --enable-sql_compat --enable-cxx
make
sudo make install

cd ../lang/php_db4/
patch db4.cpp < ../../../xpcoin-blockfile-walker/db4.cpp.patch 
phpize5.6
./configure --with-php-config=php-config5.6 --with-db4=/opt/db-6.2.32 CXXFLAGS="-D_GLIBCXX_USE_NANOSLEEP"
make CXXFLAGS=-Wno-narrowing
sudo make install

sudo echo 'extension=db4.so' > /etc/php/5.6/mods-available/db4.ini
ln -s /etc/php/5.6/mods-available/db4.ini /etc/php/5.6/fpm/conf.d/99-db4.ini
ln -s /etc/php/5.6/mods-available/db4.ini /etc/php/5.6/cli/conf.d/99-db4.ini

cd ../../../xpcoin-blockfile-walker/
cp config-sample.ini config.ini
vi config.ini

php5.6 path/to/composer.phar install

```


## Usage

### CLI

```
php5.6 cmd.php [<hash>]
```

### WEB

```
php5.6 -S 160.16.242.86:8989 public/_bootstrap.php 
```
