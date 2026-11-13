<?php
declare(strict_types=1);
namespace InnovEvents\Service;
final class EventSchedule
{
    public static function utc(mixed $value): ?string
    {
        if($value===''||$value===null)return null;
        if(!is_string($value)||!preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/',$value))throw new \InvalidArgumentException('Date invalide.');
        $utc=new \DateTimeZone('UTC');$zone=new \DateTimeZone('Europe/Paris');
        $naive=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,$utc);
        if(!$naive||$naive->format('Y-m-d\TH:i')!==$value)throw new \InvalidArgumentException('Date invalide.');
        $seconds=$naive->getTimestamp();$matches=[];
        foreach($zone->getTransitions($seconds-86400,$seconds+86400) as $transition){
            $candidate=$naive->setTimestamp($seconds-$transition['offset']);
            if($candidate->setTimezone($zone)->format('Y-m-d\TH:i')===$value)$matches[$candidate->format('Y-m-d H:i:s')]=$candidate;
        }
        if(count($matches)!==1)throw new \InvalidArgumentException('Cette heure est inexistante ou ambiguë lors du changement d’heure. Choisissez une heure non ambiguë.');
        $result=array_key_first($matches);if($result<'1000-01-01 00:00:00')throw new \InvalidArgumentException('Date hors limites.');return $result;
    }
    public static function local(?string $value): string
    {
        return $value===null?'':(new \DateTimeImmutable($value,new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d\TH:i');
    }
}
