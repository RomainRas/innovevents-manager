<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Service\MailQueue;
use PHPMailer\PHPMailer\PHPMailer;
$once=in_array('--once',$argv,true);
do {
    $db=null;
    try {
        $db=Connections::mail();$db->beginTransaction();
        $row=$db->query("SELECT *,expire_le<=UTC_TIMESTAMP() AS expired FROM courriels_a_envoyer WHERE etat='attente' AND prochain_essai_le<=UTC_TIMESTAMP() ORDER BY cree_le,id LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch();
        if (!$row) {$db->commit();if (!$once) sleep(2);continue;}
        if ($row['expired']) {
            $db->prepare("UPDATE courriels_a_envoyer SET etat='expire',contenu_chiffre=NULL WHERE id=?")->execute([$row['id']]);
        } else {
            try {
                $data=MailQueue::decode($row['contenu_chiffre']);
                $mail=new PHPMailer(true);$mail->isSMTP();$mail->Host='mailpit';$mail->Port=1025;$mail->SMTPAutoTLS=false;$mail->Timeout=5;
                $mail->CharSet='UTF-8';$mail->setFrom('contact@innovevents.example','Innov’Events');$mail->addAddress($data['email']);
                $mail->Subject=$data['subject'];$mail->Body=$data['text'];$mail->MessageID='<'.$row['id'].'@innovevents.example>';
                $mail->send();
                $db->prepare("UPDATE courriels_a_envoyer SET etat='envoye',contenu_chiffre=NULL,tentatives=tentatives+1,envoye_le=UTC_TIMESTAMP() WHERE id=?")->execute([$row['id']]);
            } catch (\Throwable $e) {
                $failed=(int)$row['tentatives']>=4;
                $q=$db->prepare("UPDATE courriels_a_envoyer SET tentatives=tentatives+1,etat=?,contenu_chiffre=?,prochain_essai_le=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 60 SECOND) WHERE id=?");
                $q->execute([$failed?'echec':'attente',$failed?null:$row['contenu_chiffre'],$row['id']]);
                error_log('Mail delivery failed: '.get_class($e));
            }
        }
        $db->commit();
    } catch (\Throwable $e) {
        if ($db && $db->inTransaction())$db->rollBack();error_log('Mail queue unavailable');if ($once)exit(1);sleep(5);
    }
} while (!$once);
