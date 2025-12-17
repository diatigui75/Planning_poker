<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use App\Services\VoteRulesService;

/**
 * Tests unitaires pour le service VoteRulesService
 * 
 * Teste l'ensemble des règles de vote Planning Poker (strict, moyenne, médiane,
 * majorité absolue, majorité relative) avec différents scénarios incluant
 * les cas limites et les votes spéciaux (?, café, ∞).
 * 
 * @package Tests\Services
 * @author Melissa Aliouche
 */
class VoteRulesServiceTest extends TestCase
{
    /**
     * Teste la règle stricte avec unanimité complète
     * 
     * Vérifie que lorsque tous les votes sont identiques,
     * la règle stricte valide le résultat avec cette valeur.
     *
     * @return void
     */
    public function testStrictRuleWithUnanimity(): void
    {
        $votes = [5, 5, 5, 5];
        $result = VoteRulesService::computeResult($votes, 'strict');
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(5, $result['value']);
        $this->assertEquals('Unanimité', $result['reason']);
    }

    /**
     * Teste la règle stricte sans unanimité
     * 
     * Vérifie que lorsque les votes diffèrent, la règle stricte
     * invalide le résultat et demande un revote.
     *
     * @return void
     */
    public function testStrictRuleWithoutUnanimity(): void
    {
        $votes = [3, 5, 5, 8];
        $result = VoteRulesService::computeResult($votes, 'strict');
        
        $this->assertFalse($result['valid']);
        $this->assertNull($result['value']);
        $this->assertEquals("Pas d'unanimité", $result['reason']);
    }

    /**
     * Teste la règle de calcul par moyenne
     * 
     * Vérifie que la moyenne arithmétique des votes est correctement
     * calculée et arrondie.
     *
     * @return void
     */
    public function testMoyenneRule(): void
    {
        $votes = [2, 4, 6, 8];
        $result = VoteRulesService::computeResult($votes, 'moyenne');
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(5, $result['value']); // (2+4+6+8)/4 = 5
        $this->assertEquals('Moyenne', $result['reason']);
    }

    /**
     * Teste l'arrondi de la règle moyenne
     * 
     * Vérifie que la moyenne est correctement arrondie à l'entier
     * le plus proche selon les règles mathématiques standard.
     *
     * @return void
     */
    public function testMoyenneRuleRounding(): void
    {
        $votes = [1, 2, 3];
        $result = VoteRulesService::computeResult($votes, 'moyenne');
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(2, $result['value']); // (1+2+3)/3 = 2
        $this->assertEquals('Moyenne', $result['reason']);
    }

    /**
     * Teste la règle médiane avec un nombre impair de votes
     * 
     * Vérifie que la médiane est correctement identifiée comme
     * la valeur centrale dans une liste triée impaire.
     *
     * @return void
     */
    public function testMedianeRuleOddCount(): void
    {
        $votes = [1, 3, 5];
        $result = VoteRulesService::computeResult($votes, 'mediane');
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(3, $result['value']);
        $this->assertEquals('Médiane', $result['reason']);
    }

    /**
     * Teste la règle médiane avec un nombre pair de votes
     * 
     * Vérifie que la médiane est calculée comme la moyenne
     * des deux valeurs centrales dans une liste triée paire.
     *
     * @return void
     */
    public function testMedianeRuleEvenCount(): void
    {
        $votes = [2, 4, 6, 8];
        $result = VoteRulesService::computeResult($votes, 'mediane');
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(5, $result['value']); // (4+6)/2 = 5
        $this->assertEquals('Médiane', $result['reason']);
    }

    /**
     * Teste la règle de majorité absolue avec une majorité claire
     * 
     * Vérifie qu'une valeur obtenant plus de 50% des votes
     * est validée comme résultat de majorité absolue.
     *
     * @return void
     */
    public function testMajoriteAbsolueWithMajority(): void
    {
        $votes = [5, 5, 5, 3, 8];
        $result = VoteRulesService::computeResult($votes, 'majorite_absolue');
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(5, $result['value']); // 3/5 > 50%
        $this->assertEquals('Majorité absolue', $result['reason']);
    }

    /**
     * Teste la règle de majorité absolue sans majorité
     * 
     * Vérifie que lorsqu'aucune valeur n'obtient plus de 50% des votes,
     * le résultat est invalidé et un revote est nécessaire.
     *
     * @return void
     */
    public function testMajoriteAbsolueWithoutMajority(): void
    {
        $votes = [3, 5, 5, 8, 8];
        $result = VoteRulesService::computeResult($votes, 'majorite_absolue');
        
        $this->assertFalse($result['valid']);
        $this->assertNull($result['value']);
        $this->assertEquals('Pas de majorité absolue', $result['reason']);
    }

    /**
     * Teste la règle de majorité relative
     * 
     * Vérifie que la valeur ayant obtenu le plus de votes
     * (pluralité) est sélectionnée comme résultat.
     *
     * @return void
     */
    public function testMajoriteRelative(): void
    {
        $votes = [3, 5, 5, 8, 13];
        $result = VoteRulesService::computeResult($votes, 'majorite_relative');
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(5, $result['value']); // 5 apparaît 2 fois
        $this->assertEquals('Majorité relative', $result['reason']);
    }

    /**
     * Teste la règle de majorité relative en cas d'égalité
     * 
     * Vérifie qu'en cas d'égalité de fréquence, la valeur
     * la plus grande est sélectionnée comme résultat.
     *
     * @return void
     */
    public function testMajoriteRelativeWithTie(): void
    {
        // En cas d'égalité, prend la plus grande valeur
        $votes = [3, 3, 8, 8];
        $result = VoteRulesService::computeResult($votes, 'majorite_relative');
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(8, $result['value']);
        $this->assertEquals('Majorité relative', $result['reason']);
    }

    /**
     * Teste le filtrage des votes non numériques
     * 
     * Vérifie que les votes spéciaux (?, café) sont correctement
     * filtrés et que seuls les votes numériques sont utilisés
     * dans le calcul pour les règles standards.
     *
     * @return void
     */
    public function testWithNonNumericVotesFiltered(): void
    {
        $votes = [5, '?', 5, 'café', 5];
        $result = VoteRulesService::computeResult($votes, 'strict');
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(5, $result['value']);
    }

    /**
     * Teste le comportement avec uniquement des votes non numériques
     * 
     * Vérifie que lorsque tous les votes sont spéciaux (?, café, ∞),
     * le résultat est invalidé car aucun calcul n'est possible.
     *
     * @return void
     */
    public function testWithOnlyNonNumericVotes(): void
    {
        $votes = ['?', 'café', '∞'];
        $result = VoteRulesService::computeResult($votes, 'moyenne');
        
        $this->assertFalse($result['valid']);
        $this->assertNull($result['value']);
        $this->assertEquals('Aucun vote numérique', $result['reason']);
    }

    /**
     * Teste le comportement avec un tableau de votes vide
     * 
     * Vérifie que la fonction gère correctement le cas limite
     * où aucun vote n'a été soumis.
     *
     * @return void
     */
    public function testWithEmptyVotes(): void
    {
        $votes = [];
        $result = VoteRulesService::computeResult($votes, 'moyenne');
        
        $this->assertFalse($result['valid']);
        $this->assertNull($result['value']);
        $this->assertEquals('Aucun vote numérique', $result['reason']);
    }

    /**
     * Teste la règle stricte avec des votes mixtes (numériques et spéciaux)
     * 
     * Vérifie que la règle stricte filtre d'abord les votes non numériques,
     * puis vérifie l'unanimité sur les votes numériques restants.
     * Dans ce cas, après filtrage il reste [5, 5] qui sont unanimes.
     *
     * @return void
     */
    public function testStrictRuleWithMixedVotes(): void
    {
        // Les votes non numériques sont filtrés, il reste [5, 5] qui sont unanimes
        $votes = [5, 5, '?'];
        $result = VoteRulesService::computeResult($votes, 'strict');
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(5, $result['value']);
        $this->assertEquals('Unanimité', $result['reason']);
    }

    /**
     * Teste le comportement avec une règle inconnue
     * 
     * Vérifie que le service utilise la majorité relative comme règle
     * par défaut lorsqu'une règle non reconnue est spécifiée.
     *
     * @return void
     */
    public function testDefaultRuleFallsBackToMajoriteRelative(): void
    {
        $votes = [3, 5, 5];
        $result = VoteRulesService::computeResult($votes, 'unknown_rule');
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(5, $result['value']);
        $this->assertEquals('Majorité relative', $result['reason']);
    }
}