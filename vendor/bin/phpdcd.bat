@ECHO OFF
SET BIN_TARGET=%~dp0\"../EHER/PHPUnit/bin"\phpdcd
php "%BIN_TARGET%" %*
