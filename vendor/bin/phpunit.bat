@ECHO OFF
SET BIN_TARGET=%~dp0\"../EHER/PHPUnit/bin"\phpunit
php "%BIN_TARGET%" %*
