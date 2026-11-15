<?php
declare(strict_types=1);
namespace InnovEvents\Tests\Unit;
use InnovEvents\Http\HttpError;
use InnovEvents\Service\AdminRecords;
use PHPUnit\Framework\TestCase;
/** Règles de saisie d'une fiche client par l'administratrice. */
final class ClientValidationTest extends TestCase
{
    private const VALID=['entreprise'=>'Horizon','contact_prenom'=>'Léa','contact_nom'=>'Martin','email'=>'lea@example.invalid','telephone'=>'+33 4 72 00 00 00'];
    private static function fields(array $input): array {
        try { AdminRecords::validateClient($input); } catch (HttpError $e) { return $e->fields; }
        self::fail('Une erreur de validation était attendue.');
    }
    public function testValidClientWithoutAddress(): void {
        $d=AdminRecords::validateClient(self::VALID);
        self::assertSame('Horizon',$d['entreprise']);
        self::assertNull($d['ville']);self::assertNull($d['pays_code'],'Pas de pays sans adresse.');
    }
    public function testAddressSetsCountry(): void {
        self::assertSame('FR',AdminRecords::validateClient(self::VALID+['adresse_ligne1'=>'3 rue fictive','code_postal'=>'69001','ville'=>'Lyon'])['pays_code']);
    }
    public function testRequiredFields(): void {
        self::assertEqualsCanonicalizing(['entreprise','contact_prenom','contact_nom','email','telephone'],array_keys(self::fields([])));
    }
    public function testEmailPhoneAndPostcodeFormats(): void {
        self::assertSame(['email','telephone','code_postal'],array_keys(self::fields(['email'=>'lea@','telephone'=>'12-34','code_postal'=>'<b>']+self::VALID)));
    }
    public function testInjectionIsKeptAsPlainText(): void {
        // Aucune interprétation : la valeur est conservée telle quelle (requêtes préparées, affichage échappé par Vue).
        self::assertSame("Horizon'); DROP TABLE clients; --",AdminRecords::validateClient(['entreprise'=>"Horizon'); DROP TABLE clients; --"]+self::VALID)['entreprise']);
    }
}
