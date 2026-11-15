<?php
declare(strict_types=1);
namespace InnovEvents\Tests\Unit;
use InnovEvents\Service\EventSchedule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
/** Conversion des dates saisies en heure de Paris vers l'UTC stocké en base, et retour. */
final class EventScheduleTest extends TestCase
{
    public static function conversions(): array {
        return [
            'hiver (UTC+1)'=>['2027-01-28T18:30','2027-01-28 17:30:00'],
            'été (UTC+2)'=>['2027-07-14T22:00','2027-07-14 20:00:00'],
            'veille de passage à l’heure d’été'=>['2027-03-27T12:00','2027-03-27 11:00:00'],
            'minuit au changement d’année'=>['2027-01-01T00:00','2026-12-31 23:00:00'],
        ];
    }
    #[DataProvider('conversions')]
    public function testConvertsParisTimeToUtc(string $local,string $utc): void {
        self::assertSame($utc,EventSchedule::utc($local));
        self::assertSame($local,EventSchedule::local($utc));
    }
    public function testEmptyValueMeansNoDate(): void {
        self::assertNull(EventSchedule::utc(''));
        self::assertNull(EventSchedule::utc(null));
        self::assertSame('',EventSchedule::local(null));
    }
    public static function invalidDates(): array {
        return [['2027-02-30T10:00'],['2027-13-01T10:00'],['2027-01-01 10:00'],['27-01-01T10:00'],['2027-01-01T25:00'],['demain'],[20270101]];
    }
    #[DataProvider('invalidDates')]
    public function testRejectsInvalidDates(mixed $value): void {
        $this->expectException(\InvalidArgumentException::class);
        EventSchedule::utc($value);
    }
    public function testRejectsNonExistentTimeWhenClocksGoForward(): void {
        // 28 mars 2027 : on passe de 2 h à 3 h, 2 h 30 n'existe pas à Paris.
        $this->expectExceptionMessageMatches('/inexistante ou ambiguë/');
        EventSchedule::utc('2027-03-28T02:30');
    }
    public function testRejectsAmbiguousTimeWhenClocksGoBack(): void {
        // 31 octobre 2027 : 2 h 30 existe deux fois (avant et après le retour à l'heure d'hiver).
        $this->expectExceptionMessageMatches('/inexistante ou ambiguë/');
        EventSchedule::utc('2027-10-31T02:30');
    }
}
