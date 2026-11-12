<?php
declare(strict_types=1);
namespace InnovEvents\Security;
final class LoginLimiter
{
    // Choix local : 10 tentatives / compte et 60 / pair réseau en 15 minutes.
    public function allow(string $email,string $ip): bool
    {
        $dir='/var/lib/innovevents';
        if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new \RuntimeException('Rate storage');
        $f=fopen($dir.'/login-rate.json','c+');
        if (!$f || !flock($f,LOCK_EX)) throw new \RuntimeException('Rate lock');
        try {
            $raw=stream_get_contents($f);
            $data=$raw===''?[]:json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            $now=time();
            foreach ($data as $key=>$entry) if ($entry['until']<=$now) unset($data[$key]);
            $keys=[hash('sha256','account:'.$email)=>10,hash('sha256','ip:'.$ip)=>60];
            $allowed=true;
            foreach ($keys as $key=>$max) if (($data[$key]['count']??0)>=$max) $allowed=false;
            if ($allowed) foreach ($keys as $key=>$max) {
                $data[$key]??=['count'=>0,'until'=>$now+900];
                $data[$key]['count']++;
            }
            rewind($f); ftruncate($f,0);
            $json=json_encode($data,JSON_THROW_ON_ERROR);
            if (fwrite($f,$json)!==strlen($json) || !fflush($f)) throw new \RuntimeException('Rate write');
            return $allowed;
        } finally {flock($f,LOCK_UN);fclose($f);}
    }
}
