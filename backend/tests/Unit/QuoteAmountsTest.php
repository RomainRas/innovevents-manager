<?php
declare(strict_types=1);
namespace InnovEvents\Tests\Unit;
use InnovEvents\Service\QuoteAmounts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
/** Calcul exact des devis en centimes (reprend tests/quote-amounts.php, exécuté aussi par l'intégration continue). */
final class QuoteAmountsTest extends TestCase
{
    public static function totals(): array {
        return [['100.00','20','20.00','120.00'],['0.05','10','0.01','0.06'],['0.01','49.99','0.00','0.01'],['0.01','50','0.01','0.02'],
            ['1,23','5,5','0.07','1.30'],['0','0','0.00','0.00'],['9999999.99','100','9999999.99','19999999.98']];
    }
    #[DataProvider('totals')]
    public function testTaxAndTotal(string $amount,string $rate,string $tax,string $total): void {
        $d=QuoteAmounts::calculate(['prestations'=>[['libelle'=>'Test','montant_ht'=>$amount]],'taux_tva'=>$rate]);
        self::assertSame($tax,$d['total_tva']);self::assertSame($total,$d['total_ttc']);
    }
    public function testRoundingOnTotalNotPerLine(): void {
        self::assertSame('0.01',QuoteAmounts::calculate(['prestations'=>[['libelle'=>'A','montant_ht'=>'0.05'],['libelle'=>'B','montant_ht'=>'0.05']],'taux_tva'=>'10'])['total_tva']);
    }
    public static function invalidAmounts(): array {return [['-1'],['1.001'],['1e3'],['NaN'],['1 000'],['10000000'],[1.25]];}
    #[DataProvider('invalidAmounts')]
    public function testInvalidAmountsAreRejected(mixed $amount): void {
        $this->expectException(\InvalidArgumentException::class);
        QuoteAmounts::cents($amount);
    }
    public function testMaximumQuoteStaysExact(): void {
        self::assertSame('1999999998.00',QuoteAmounts::calculate(['prestations'=>array_fill(0,100,['libelle'=>'Maximum','montant_ht'=>'9999999.99']),'taux_tva'=>'100'])['total_ttc']);
    }
}
