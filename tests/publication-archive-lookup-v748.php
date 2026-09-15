<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/PublicationArchiveLookup.php';
use App\Services\PublicationArchiveLookup;

class ArchiveStatement748 extends PDOStatement {
    public array $rows;
    public function __construct(array $rows) { $this->rows=$rows; }
    public function execute(?array $params=null): bool { return true; }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0): mixed { return $this->rows[0]??false; }
}
class ArchivePDO748 extends PDO {
    public array $sql=[];
    public function __construct(public array $responses) {}
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        $this->sql[]=$query;
        if (!$this->responses) throw new RuntimeException('Unexpected query');
        return new ArchiveStatement748(array_shift($this->responses));
    }
}
function check748(bool $ok): void { if (!$ok) throw new RuntimeException('Archive lookup assertion failed'); }
function fails748(callable $test,string $message): void {
    try { $test(); } catch (RuntimeException $e) { check748(str_contains($e->getMessage(),$message)); return; }
    throw new RuntimeException('Expected lookup rejection');
}
$complete=['heats'=>['id'=>11],'finals'=>['id'=>12],'points'=>['id'=>13]];
$pdo=new ArchivePDO748([]);
check748(PublicationArchiveLookup::recover($pdo,68,9,'bdc_result_documents',$complete)===$complete);
check748($pdo->sql===[]);
$audit=['details_json'=>json_encode(['publication_id'=>9,'documents'=>['heats'=>11,'finals'=>12,'points'=>13]]),'event_id'=>161];
foreach (['bdc_result_documents','bdc_test_result_documents'] as $table) {
    $pdo=new ArchivePDO748([[$audit],[['id'=>11,'document_category'=>'heats']],[['id'=>12,'document_category'=>'finals']],[['id'=>13,'document_category'=>'points']]]);
    $result=PublicationArchiveLookup::recover($pdo,68,9,$table,[]);
    check748(count($result)===3 && $result['heats']['id']===11);
    check748(str_contains($pdo->sql[1],$table));
    check748(str_contains($pdo->sql[1],'event_id=:event_id'));
    check748(str_contains($pdo->sql[0],$table==='bdc_test_result_documents'?'bdc_test_scoring_audit':'bdc_scoring_audit'));
}
fails748(fn()=>PublicationArchiveLookup::recover(new ArchivePDO748([[]]),68,9,'bdc_result_documents',[]),'mapping is missing');
$wrong=$audit; $wrong['details_json']=json_encode(['publication_id'=>10,'documents'=>['heats'=>11]]);
fails748(fn()=>PublicationArchiveLookup::recover(new ArchivePDO748([[$wrong]]),68,9,'bdc_result_documents',[]),'mapping is missing');
$conflict=$audit; $conflict['details_json']=json_encode(['publication_id'=>9,'documents'=>['heats'=>99]]);
fails748(fn()=>PublicationArchiveLookup::recover(new ArchivePDO748([[$audit,$conflict]]),68,9,'bdc_result_documents',[]),'Conflicting');
fails748(fn()=>PublicationArchiveLookup::recover(new ArchivePDO748([[$audit],[]]),68,9,'bdc_result_documents',[]),'does not belong');
fails748(fn()=>PublicationArchiveLookup::recover(new ArchivePDO748([]),68,9,'invalid',[]),'Invalid');
echo "Publication archive lookup v748 checks passed\n";
