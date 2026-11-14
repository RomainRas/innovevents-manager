<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use Dompdf\Dompdf;
use Dompdf\Options;
final class QuotePdfRenderer
{
    public static function render(array $s,string $image): string {
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $money=static function(string $v): string {[$a,$b]=explode('.',$v);return preg_replace('/\B(?=(\d{3})+(?!\d))/',' ',$a).','.$b.' €';};
        $date=static fn($v)=>(new \DateTimeImmutable($v,new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i');
        $v=$s['quote'];$c=$s['client'];$ev=$s['event'];$rows='';
        foreach($s['prestations'] as $i=>$line)$rows.='<tr><td class="index">'.($i+1).'</td><td class="description">'.$e($line['libelle']).'</td><td class="amount">'.$e($money($line['montant_ht'])).'</td></tr>';
        $mime=str_ends_with($ev['image_reference'],'.png')?'image/png':'image/jpeg';
        $html='<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>
        @page{margin:36pt 40pt 46pt}body{font-family:"DejaVu Sans";font-size:10pt;color:#142D43;line-height:1.4}h1{font-size:26pt;margin:0;color:#087F8C}h2{font-size:13pt;margin:18pt 0 6pt}.brand{font-size:14pt;font-weight:bold}.muted{font-size:8pt;color:#526473}.box{background:#F4F7FA;padding:12pt;margin:12pt 0}.event-image{max-width:170pt;max-height:85pt;margin:8pt 0}table{width:100%;border-collapse:collapse;table-layout:fixed}thead{display:table-header-group}th{background:#142D43;color:white;text-align:left;padding:8pt}td{padding:8pt;border-bottom:1pt solid #DDE5EC;vertical-align:top;word-wrap:break-word}tr{page-break-inside:avoid}.amount{text-align:right;white-space:nowrap}.index{width:6%;white-space:nowrap}.description{width:70%}.amount{width:24%}.totals{page-break-inside:avoid;margin-top:14pt}.totals td{padding:5pt 8pt}.grand{background:#E6F3F4;font-weight:bold;font-size:12pt}</style></head><body>
        <div class="brand">Innov’Events</div><h1>Devis</h1><p>'.$e($v['reference']).' · Version '.$e($v['numero']).' · Révision '.$e($v['revision_contenu']).'</p>
        <p class="muted">Document de démonstration ECF — non transmis. Coordonnées juridiques de l’agence à compléter avant usage commercial.</p>
        <div class="box"><strong>Client : '.$e($c['entreprise']).'</strong><br>'.$e($c['contact_prenom'].' '.$c['contact_nom']).'<br>'.$e($c['email']).'<br>'.$e($c['telephone']).'</div>
        <h2>'.$e($ev['nom']).'</h2><p>'.$e($ev['lieu']).'<br>Du '.$e($date($ev['debut'])).' au '.$e($date($ev['fin'])).' (heure de Paris)</p>
        <img class="event-image" alt="'.$e($ev['image_alternative']).'" src="data:'.$mime.';base64,'.base64_encode($image).'">
        <h2>Prestations</h2><table><thead><tr><th class="index">N°</th><th class="description">Libellé</th><th class="amount">Montant HT</th></tr></thead><tbody>'.$rows.'</tbody></table>
        <table class="totals"><tr><td>Total HT</td><td class="amount">'.$e($money($v['total_ht'])).'</td></tr><tr><td>TVA ('.$e(str_replace('.',',',$v['taux_tva'])).' %)</td><td class="amount">'.$e($money($v['total_tva'])).'</td></tr><tr class="grand"><td>Total TTC</td><td class="amount">'.$e($money($v['total_ttc'])).'</td></tr></table>
        <p class="muted">Montants en EUR. TVA arrondie au centime sur le total HT. Ce téléchargement ne vaut ni envoi ni acceptation du devis.</p></body></html>';
        $cache='/var/lib/innovevents/pdf-cache';if(!is_dir($cache)&&!mkdir($cache,0700,true)&&!is_dir($cache))throw new \RuntimeException('PDF cache');
        $options=new Options(['isRemoteEnabled'=>false,'isPhpEnabled'=>false,'isJavascriptEnabled'=>false,'defaultFont'=>'DejaVu Sans','tempDir'=>$cache,'fontCache'=>$cache,'chroot'=>[dirname(__DIR__,2).'/vendor/dompdf']]);
        $pdf=new Dompdf($options);$pdf->loadHtml($html,'UTF-8');$pdf->setPaper('A4');$pdf->render();
        $pdf->getCanvas()->page_text(40,810,'Innov’Events — '.$v['reference'].' | Page {PAGE_NUM} / {PAGE_COUNT}',$pdf->getFontMetrics()->getFont('DejaVu Sans'),8,[0.32,0.39,0.45]);
        return $pdf->output();
    }
}
