<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Service\QuoteAmounts as Q;
$results=[];
$cases=[['100.00','20','20.00','120.00'],['0.05','10','0.01','0.06'],['0.01','49.99','0.00','0.01'],['0.01','50','0.01','0.02'],['1,23','5,5','0.07','1.30'],['0','0','0.00','0.00'],['9999999.99','100','9999999.99','19999999.98']];
foreach($cases as $i=>[$amount,$rate,$tax,$ttc]){$d=Q::calculate(['prestations'=>[['libelle'=>'Test','montant_ht'=>$amount]],'taux_tva'=>$rate]);$results['case_'.$i]=$d['total_tva']===$tax&&$d['total_ttc']===$ttc;}
$d=Q::calculate(['prestations'=>[['libelle'=>'A','montant_ht'=>'0.05'],['libelle'=>'B','montant_ht'=>'0.05']],'taux_tva'=>'10']);$results['round_on_total']=$d['total_tva']==='0.01';
foreach(['-1','1.001','1e3','NaN','1 000','10000000',1.25] as $i=>$bad){try{Q::cents($bad);$results['invalid_'.$i]=false;}catch(InvalidArgumentException){$results['invalid_'.$i]=true;}}
$d=Q::calculate(['prestations'=>array_fill(0,100,['libelle'=>'Maximum','montant_ht'=>'9999999.99']),'taux_tva'=>'100']);$results['maximum_exact']=$d['total_ttc']==='1999999998.00';
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;exit(in_array(false,$results,true)?1:0);
