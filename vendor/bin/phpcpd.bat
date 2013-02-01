@ECHO OFF
SET BIN_TARGET=%~dp0\"../EHER/PHPUnit/bin"\phpcpd
php "%BIN_TARGET%" %*
