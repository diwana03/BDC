<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;

final class JudgeLinkDeliveryService
{
    public static function ensure(PDO $pdo):void
    {
        JudgeDirectoryService::ensure($pdo);
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_judge_link_deliveries(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,data_mode ENUM('real','test') NOT NULL,round_id BIGINT UNSIGNED NOT NULL,assignment_id BIGINT UNSIGNED NOT NULL,judge_directory_id BIGINT UNSIGNED NULL,channel ENUM('email','whatsapp') NOT NULL,recipient VARCHAR(190) NOT NULL,status VARCHAR(24) NOT NULL,details VARCHAR(500) NULL,sent_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_judge_delivery_round(data_mode,round_id),INDEX idx_judge_delivery_assignment(assignment_id),INDEX idx_judge_delivery_created(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function contact(PDO $pdo,int $assignmentId,int $roundId,bool $test):array
    {
        self::ensure($pdo);JudgeDirectoryService::backfillAssignments($pdo);
        $table=$test?'bdc_test_scoring_judges':'bdc_scoring_judges';
        $stmt=$pdo->prepare("SELECT a.id,a.judge_id,a.judge_name,j.email,j.phone,j.whatsapp,j.preferred_contact FROM {$table} a LEFT JOIN bdc_judges j ON j.id=a.judge_id WHERE a.id=:assignment AND a.round_id=:round LIMIT 1");
        $stmt->execute(['assignment'=>$assignmentId,'round'=>$roundId]);
        $row=$stmt->fetch();if(!$row)throw new RuntimeException('Judge assignment was not found.');
        return $row;
    }

    public static function sendEmail(PDO $pdo,array $contact,int $roundId,bool $test,string $subject,string $body,int $userId):void
    {
        $email=trim((string)($contact['email']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('This judge has no valid email in the Judge Database.');
        $headers="From: no-reply@bachatadancecouncil.com\r\nReply-To: no-reply@bachatadancecouncil.com\r\nContent-Type: text/plain; charset=UTF-8";
        $ok=@mail($email,$subject,$body,$headers);
        self::record($pdo,$test,$roundId,$contact,'email',$email,$ok?'queued':'failed',$ok?'Accepted by the website mail server.':'The website mail server rejected the message.',$userId);
        if(!$ok)throw new RuntimeException('Email could not be accepted by the website mail server. Copy the link and contact the judge manually.');
    }

    public static function whatsappUrl(PDO $pdo,array $contact,int $roundId,bool $test,string $body,int $userId):string
    {
        $raw=trim((string)($contact['whatsapp']?:$contact['phone']??''));$number=preg_replace('/\D+/','',$raw)??'';
        if($number==='')throw new RuntimeException('This judge has no WhatsApp number in the Judge Database.');
        self::record($pdo,$test,$roundId,$contact,'whatsapp',$number,'opened','Opened a pre-addressed WhatsApp message.',$userId);
        return 'https://wa.me/'.$number.'?text='.rawurlencode($body);
    }

    private static function record(PDO $pdo,bool $test,int $roundId,array $contact,string $channel,string $recipient,string $status,string $details,int $userId):void
    {
        $stmt=$pdo->prepare('INSERT INTO bdc_judge_link_deliveries(data_mode,round_id,assignment_id,judge_directory_id,channel,recipient,status,details,sent_by) VALUES(:mode,:round,:assignment,:judge,:channel,:recipient,:status,:details,:user)');
        $stmt->execute(['mode'=>$test?'test':'real','round'=>$roundId,'assignment'=>$contact['id'],'judge'=>$contact['judge_id']?:null,'channel'=>$channel,'recipient'=>$recipient,'status'=>$status,'details'=>$details,'user'=>$userId?:null]);
    }
}
