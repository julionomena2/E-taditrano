<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MessagerieApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_guest_requests_messaging_conversations(): void
    {
        $this->getJson('/api/messagerie/conversations')
            ->assertUnauthorized();
    }

    public function test_conversations_show_the_latest_message_for_each_contact(): void
    {
        $utilisateur = $this->creerUtilisateur('awa@example.mg', 'Rabe', 'Awa');
        $contactRecent = $this->creerUtilisateur('miora@example.mg', 'Raman', 'Miora');
        $autreContact = $this->creerUtilisateur('solo@example.mg', 'Solo', 'Sitraka');

        Message::create([
            'expediteur_id' => $utilisateur->getKey(),
            'destinataire_id' => $autreContact->getKey(),
            'contenu' => 'Ancienne conversation',
        ]);
        Message::create([
            'expediteur_id' => $contactRecent->getKey(),
            'destinataire_id' => $utilisateur->getKey(),
            'contenu' => 'Dernier message',
        ]);
        Message::create([
            'expediteur_id' => $utilisateur->getKey(),
            'destinataire_id' => $autreContact->getKey(),
            'contenu' => 'Réponse récente',
        ]);
        $this->actingAs($utilisateur, 'utilisateur');

        $this->getJson('/api/messagerie/conversations')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.contact.id_utilisateur', 'solo@example.mg')
            ->assertJsonPath('0.dernier_message.contenu', 'Réponse récente')
            ->assertJsonPath('1.contact.id_utilisateur', 'miora@example.mg')
            ->assertJsonPath('1.dernier_message.contenu', 'Dernier message');
    }

    public function test_conversations_include_only_profiles_with_at_least_one_message(): void
    {
        $utilisateur = $this->creerUtilisateur('awa@example.mg', 'Rabe', 'Awa');
        $contactAvecMessage = $this->creerUtilisateur('miora@example.mg', 'Raman', 'Miora');
        $this->creerUtilisateur('sans-message@example.mg', 'Solo', 'Sitraka');
        Message::create([
            'expediteur_id' => $contactAvecMessage->getKey(),
            'destinataire_id' => $utilisateur->getKey(),
            'contenu' => 'Premier message reçu',
        ]);
        $this->actingAs($utilisateur, 'utilisateur');

        $this->getJson('/api/messagerie/conversations')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.contact.id_utilisateur', 'miora@example.mg');
    }

    public function test_returns_only_messages_between_the_authenticated_user_and_contact(): void
    {
        $utilisateur = $this->creerUtilisateur('awa@example.mg', 'Rabe', 'Awa');
        $contact = $this->creerUtilisateur('miora@example.mg', 'Raman', 'Miora');
        $autreUtilisateur = $this->creerUtilisateur('solo@example.mg', 'Solo', 'Sitraka');

        Message::create([
            'expediteur_id' => $utilisateur->getKey(),
            'destinataire_id' => $contact->getKey(),
            'contenu' => 'Bonjour',
        ]);
        Message::create([
            'expediteur_id' => $contact->getKey(),
            'destinataire_id' => $utilisateur->getKey(),
            'contenu' => 'Bonjour Awa',
        ]);
        Message::create([
            'expediteur_id' => $contact->getKey(),
            'destinataire_id' => $autreUtilisateur->getKey(),
            'contenu' => 'Message prive',
        ]);
        $this->actingAs($utilisateur, 'utilisateur');

        $this->getJson('/api/messagerie/miora@example.mg/messages')
            ->assertOk()
            ->assertJsonPath('contact.id_utilisateur', 'miora@example.mg')
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.contenu', 'Bonjour')
            ->assertJsonPath('messages.1.contenu', 'Bonjour Awa');
    }

    public function test_saves_message_with_authenticated_sender_and_returns_created_message(): void
    {
        $utilisateur = $this->creerUtilisateur('awa@example.mg', 'Rabe', 'Awa');
        $contact = $this->creerUtilisateur('miora@example.mg', 'Raman', 'Miora');
        $this->actingAs($utilisateur, 'utilisateur');

        $this->postJson('/api/messagerie/miora@example.mg/messages', [
            'contenu' => 'Bonjour Miora',
            'expediteur_id' => $contact->getKey(),
        ])
            ->assertCreated()
            ->assertJsonPath('expediteur_id', 'awa@example.mg')
            ->assertJsonPath('destinataire_id', 'miora@example.mg')
            ->assertJsonPath('contenu', 'Bonjour Miora');

        $this->assertDatabaseHas('messages', [
            'expediteur_id' => 'awa@example.mg',
            'destinataire_id' => 'miora@example.mg',
            'contenu' => 'Bonjour Miora',
        ]);
    }

    public function test_rejects_a_message_longer_than_2000_characters(): void
    {
        $utilisateur = $this->creerUtilisateur('awa@example.mg', 'Rabe', 'Awa');
        $contact = $this->creerUtilisateur('miora@example.mg', 'Raman', 'Miora');
        $this->actingAs($utilisateur, 'utilisateur');

        $this->postJson('/api/messagerie/miora@example.mg/messages', [
            'contenu' => str_repeat('a', 2001),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'contenu' => 'Votre message ne peut pas dépasser 2 000 caractères.',
            ]);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_rejects_an_empty_message_with_a_helpful_validation_error(): void
    {
        $utilisateur = $this->creerUtilisateur('awa@example.mg', 'Rabe', 'Awa');
        $this->creerUtilisateur('miora@example.mg', 'Raman', 'Miora');
        $this->actingAs($utilisateur, 'utilisateur');

        $this->postJson('/api/messagerie/miora@example.mg/messages', [
            'contenu' => '   ',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'contenu' => 'Écrivez un message avant de l’envoyer.',
            ]);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_returns_404_when_user_opens_a_conversation_with_themselves(): void
    {
        $utilisateur = $this->creerUtilisateur('awa@example.mg', 'Rabe', 'Awa');
        $this->actingAs($utilisateur, 'utilisateur');

        $this->getJson('/api/messagerie/awa@example.mg/messages')
            ->assertNotFound();

        $this->postJson('/api/messagerie/awa@example.mg/messages', [
            'contenu' => 'Message a moi-meme',
        ])->assertNotFound();

        $this->assertDatabaseCount('messages', 0);
    }

    private function creerUtilisateur(string $email, string $nom, string $prenom): Utilisateur
    {
        return Utilisateur::create([
            'id_utilisateur' => $email,
            'nom' => $nom,
            'prenom' => $prenom,
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0341234567',
        ]);
    }
}
