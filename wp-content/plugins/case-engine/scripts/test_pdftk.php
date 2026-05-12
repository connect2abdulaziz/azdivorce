<?php
$bin  = 'C:\\Program Files (x86)\\PDFtk Server\\bin\\pdftk.exe';
$out  = [];
$code = 1;
exec( escapeshellarg( $bin ) . ' --version 2>&1', $out, $code );
echo 'exit: ' . $code . "\n";
echo isset( $out[0] ) ? $out[0] : 'no output';
echo "\n";
echo 'file_exists: ' . ( file_exists( $bin ) ? 'yes' : 'no' ) . "\n";
