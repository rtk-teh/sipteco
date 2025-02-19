# sipteco

1. Install apache and configurate
	
	Instalation:
		sudo apt update
	   	sudo apt install apache2

   	Configuration:
	   	Change DocumentRoot to /var/www/sipteco/public 
	   	
	   	Add in Virtual host:
	   	<Directory /var/www/sipteco/public>
	        Options Indexes FollowSymLinks
	        AllowOverride All
	        Require all granted
	    </Directory>


2. Install php

	sudo apt install php libapache2-mod-php

3. Install composer
   
	php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
	php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }"
	php composer-setup.php
	php -r "unlink('composer-setup.php');"
	sudo mv composer.phar /usr/local/bin/composer

5. Install MySQL

	sudo apt install mysql-server

6. Install phpMyAdmin
	
	sudo apt install phpmyadmin

	credintials:
		user     = debian-sys-maint
		password = yP01y1amf5JTU9xm

	database: sipteco

7. Install gh

	sudo apt install gh

8. Clone project
	
	git clone https://github.com/rtk-teh/sipteco.git

9. Change dbusername and password in .env

10. Install npm

	sudo apt install npm

9. Run npm

	npm install
	npm run build

10. Update dependecies

	composer update

11. Migrate DB
	
	php artisan migrate

12. Clone admin user to migrated table `users`

13. Add .htaccess file to project directory:
	<IfModule mod_rewrite.c>
		Options +FollowSymLinks
		RewriteEngine On
		RewriteCond %{REQUEST_URI} !^/public/
		RewriteCond %{REQUEST_FILENAME} !-d
		RewriteCond %{REQUEST_FILENAME} !-f
		RewriteRule ^(.*)$ /public/$1
		#RewriteRule ^ index.php [L]
		RewriteRule ^(/)?$ public/index.php [L]
	</IfModule>

14. Restart apache2
	systemctl restart apache2

15. Allow storage folders
	chmod -R gu+w storage
   	chmod -R guo+w storage

16. Generate keys
	php artisan key:generate
	php artisan config:cache


15. Open server ip in browser, sign in, use!
