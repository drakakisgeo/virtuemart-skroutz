@ECHO OFF
SET BIN_TARGET=%~dp0\"../EHER/PHPUnit/bin"\phploc
php "%BIN_TARGET%" %*
