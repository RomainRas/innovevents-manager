<?php
declare(strict_types=1);
namespace InnovEvents\Tests\Unit;
use InnovEvents\Http\HttpError;
use InnovEvents\Service\AdminRecords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
/** Règles de saisie d'un événement par l'administratrice (fonctionnalité testée en unitaire, fonctionnel et de bout en bout). */
final class EventValidationTest extends TestCase
{
    private const VALID=['nom'=>'Gala de printemps','debut'=>'2027-04-15T18:00','fin'=>'2027-04-15T23:30','lieu'=>'Salle fictive, Lyon',
        'type_evenement'=>'Soirée d’entreprise','theme'=>'Gala','participants_estimes'=>'120'];
    private static function fields(array $input): array {
        try { AdminRecords::validateEvent($input); } catch (HttpError $e) { self::assertSame(422,$e->status); return $e->fields; }
        self::fail('Une erreur de validation était attendue.');
    }
    public function testValidEventIsNormalized(): void {
        $d=AdminRecords::validateEvent(['nom'=>'  Gala  ']+self::VALID);
        self::assertSame('Gala',$d['nom']);
        self::assertSame('2027-04-15 16:00:00',$d['debut'],'Heure de Paris convertie en UTC.');
        self::assertSame(120,$d['participants_estimes']);
    }
    public function testOptionalFieldsBecomeNull(): void {
        $d=AdminRecords::validateEvent(['nom'=>'Brouillon','debut'=>'','fin'=>'','lieu'=>' ','participants_estimes'=>'']);
        self::assertNull($d['debut']);self::assertNull($d['fin']);self::assertNull($d['lieu']);self::assertNull($d['participants_estimes']);
    }
    public function testNameIsRequired(): void {
        self::assertArrayHasKey('nom',self::fields(['nom'=>'   ']+self::VALID));
    }
    public function testEndMustFollowStart(): void {
        self::assertSame(['fin'=>'La fin doit être postérieure au début.'],self::fields(['fin'=>'2027-04-15T18:00']+self::VALID));
        self::assertArrayHasKey('fin',self::fields(['fin'=>'2027-04-15T17:00']+self::VALID));
    }
    public static function badParticipants(): array {return [['0'],['-5'],['1.5'],['abc'],['99999999999'],[12.0]];}
    #[DataProvider('badParticipants')]
    public function testParticipantsMustBePositiveInteger(mixed $value): void {
        self::assertArrayHasKey('participants_estimes',self::fields(['participants_estimes'=>$value]+self::VALID));
    }
    public function testLengthAndControlCharactersAreRejected(): void {
        $f=self::fields(['nom'=>str_repeat('é',201),'lieu'=>"Lyon\x00",'theme'=>['tableau']]+self::VALID);
        self::assertSame(['nom','lieu','theme'],array_keys($f));
    }
    public function testInvalidDateIsReportedOnTheField(): void {
        self::assertArrayHasKey('debut',self::fields(['debut'=>'2027-02-30T10:00']+self::VALID));
    }
    public function testSeveralErrorsAreReportedTogether(): void {
        self::assertEqualsCanonicalizing(['nom','debut','participants_estimes'],array_keys(self::fields(['nom'=>'','debut'=>'x','participants_estimes'=>'0']+self::VALID)));
    }
}
