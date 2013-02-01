@ECHO OFF
SET BIN_TARGET=%~dp0\"../EHER/PHPUnit/bin"\dbunit
php "%BIN_TARGET%" %*
