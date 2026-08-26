<?php
declare(strict_types=1);

ob_start(static function(string $html)use($pdo,&$status):string{
    try{
        $where=$status==='all'?'':' AND r.status=:status';
        $query=$pdo->prepare("SELECT r.id,r.full_name,r.status,d.event_name,d.category_name,d.dance_style,d.entry_type,d.competition_level,d.partner_or_team_name,d.team_size FROM bdc_profile_request_dance_cup_categories d JOIN bdc_profile_requests r ON r.id=d.request_id WHERE 1=1{$where} ORDER BY r.created_at DESC,r.id DESC,d.event_name,d.category_name");
        $query->execute($status==='all'?[]:['status'=>$status]);$rows=$query->fetchAll();
        if(!$rows)return $html;
        $summary='<section class="card border-primary shadow-sm mb-4"><div class="card-body"><h2 class="h5">BDC Dance Cup category requests</h2><p class="text-muted small">These selections are linked to the organiser-configured event categories and do not change permanent competitor divisions.</p><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Request</th><th>Competitor</th><th>Event</th><th>Category</th><th>Format</th><th>Partner / Team</th></tr></thead><tbody>';
        foreach($rows as $row){$format=ucwords(str_replace('_',' ',(string)$row['dance_style'])).' · '.ucwords(str_replace('_',' ',(string)$row['entry_type'])).' · '.ucwords(str_replace('_',' ',(string)$row['competition_level']));$partner=(string)($row['partner_or_team_name']?:'—');if($row['team_size'])$partner.=' · '.(int)$row['team_size'].' dancers';$summary.='<tr><td>#'.(int)$row['id'].'</td><td>'.e((string)$row['full_name']).'</td><td>'.e((string)$row['event_name']).'</td><td><strong>'.e((string)$row['category_name']).'</strong></td><td>'.e($format).'</td><td>'.e($partner).'</td></tr>';}
        $summary.='</tbody></table></div></div></section>';
        return str_replace('<div class="btn-group flex-wrap mb-4">',$summary.'<div class="btn-group flex-wrap mb-4">',$html);
    }catch(Throwable $ignored){return $html;}
});
