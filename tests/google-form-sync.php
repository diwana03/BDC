<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Services/CompetitorIdentityService.php';
require dirname(__DIR__).'/app/Services/GoogleFormSyncService.php';

use App\Services\GoogleFormSyncService;

function expect(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}

$open=GoogleFormSyncService::canonicalPayload(['form_kind'=>'open','source_key'=>'sheet:1','full_name'=>' Test Person ','email'=>'TEST@EXAMPLE.COM','phone'=>'+65 9123 4567','instagram'=>'https://instagram.com/Test.Person/','role'=>'Follow (Salsa + Bachata J&J)','styles'=>'Salsa + Bachata']);
expect($open['role']==='follower','Follow mapping failed.');
expect($open['styles']===['salsa','bachata'],'Dual-style mapping failed.');
expect($open['instagram']==='test.person','Instagram normalisation failed.');
expect($open['email']==='test@example.com','Email normalisation failed.');

$candidate=['id'=>8,'normalised_name'=>'test person','email'=>'test@example.com','phone'=>'','instagram'=>''];
$decision=GoogleFormSyncService::resolveIdentity($open,[$candidate]);
expect($decision['status']==='existing'&&$decision['competitor_id']===8,'Unique identifier match must reuse the BDC identity.');

$ambiguous=GoogleFormSyncService::resolveIdentity($open,[
    ['id'=>8,'normalised_name'=>'test person','email'=>'test@example.com','phone'=>'','instagram'=>''],
    ['id'=>9,'normalised_name'=>'test person','email'=>'test@example.com','phone'=>'','instagram'=>''],
]);
expect($ambiguous['status']==='pending_review','Ambiguous duplicate identities must remain pending.');

$new=GoogleFormSyncService::resolveIdentity($open,[]);
expect($new['status']==='new','No candidates must create a new identity.');
echo "Google Form sync tests passed.\n";
