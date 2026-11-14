<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Http\HttpError;
final class QuoteAmounts
{
    public static function cents(mixed $value,int $max=999999999): int {
        if(!is_string($value)||!preg_match('/^(0|[1-9][0-9]{0,8})(?:[.,]([0-9]{1,2}))?$/D',$value,$m))throw new \InvalidArgumentException('Montant invalide');
        $n=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');if($n>$max)throw new \InvalidArgumentException('Montant hors limites');return $n;
    }
    public static function decimal(int $cents): string {return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);}
    public static function calculate(array $input): array {
        $errors=[];$lines=[];$total=0;
        try{$rate=self::cents($input['taux_tva']??null,10000);}catch(\InvalidArgumentException){$errors['taux_tva']='Saisissez un taux de 0 à 100, avec deux décimales maximum.';}
        $rows=$input['prestations']??null;
        if(!is_array($rows)||!array_is_list($rows)||count($rows)<1||count($rows)>100)$errors['prestations']='Ajoutez de 1 à 100 prestations.';
        else foreach($rows as $i=>$row){
            $label=is_array($row)?($row['libelle']??null):null;
            if(!is_string($label)||trim($label)===''||preg_match('//u',$label)!==1||preg_match_all('/./us',$label)>255||preg_match('/[\x00-\x1F\x7F]/',$label))$errors['libelle-'.$i]='Libellé obligatoire, 255 caractères maximum.';
            try{$amount=self::cents(is_array($row)?($row['montant_ht']??null):null);$total+=$amount;}catch(\InvalidArgumentException){$errors['montant-'.$i]='Montant positif ou nul, jusqu’à 9 999 999,99 €, deux décimales maximum.';$amount=0;}
            $lines[]=['libelle'=>is_string($label)?trim($label):'','montant_ht'=>self::decimal($amount)];
        }
        if($errors)throw new HttpError(422,'validation_failed',$errors);
        // Scaled integers: cents × hundredths of a percent; half rounded upwards.
        $tax=intdiv($total*$rate+5000,10000);
        return ['prestations'=>$lines,'taux_tva'=>self::decimal($rate),'total_ht'=>self::decimal($total),'total_tva'=>self::decimal($tax),'total_ttc'=>self::decimal($total+$tax),'devise'=>'EUR','regle_calcul'=>'TVA_TOTAL_2D_HALF_UP_V1'];
    }
}
