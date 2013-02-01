@ECHO OFF
SET BIN_TARGET=%~dp0\"../EHER/PHPUnit/bin"\phpcov
php "%BIN_TARGET%" %*
