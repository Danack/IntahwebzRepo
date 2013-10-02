IntahwebzRepo
=============

A bastion server for deploying other composer apps.

This is just an example implementation of how to actually use Satis in a way that removes your dependency on either Packagist and Github being available, as well as providing some defence against:

* MITM attacks against Packagist.

* The horrendous behaviour of 'Replaces' in Composer.
 
* Malicious uploads by someone who has access to a package that you depend on.

See [here](https://docs.google.com/presentation/d/1Et9xYeFo4RrpBnGycz5XM0rhlDk2c7MRBvv8mMEbPtc/edit?usp=sharing) for more details - the 'Running your own Packagist' slides.

The list of packages it serves is effectively hard-coded for my project.


Running the server
==================

`php fetchZips.php` - Fetches and repackages all the dependencies that my project depends on.
 
`php vendor/bin/satis build satis.json danack/` - Runs Satis and tells it to build pacakages into the directory danack

`php -S localhost:8000 -t danack/` - Runs a webserver to run requests.

 
 
 
 


