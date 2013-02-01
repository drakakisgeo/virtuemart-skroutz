@ECHO OFF
SET BIN_TARGET=%~dp0\"../EHER/PHPUnit/bin"\phpunit-skelgen
php "%BIN_TARGET%" %*
