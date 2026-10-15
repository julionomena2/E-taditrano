<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessagerieController extends Controller
{
    public function conversations(): JsonResponse
    {
        $utilisateurId = Auth::guard('utilisateur')->id();

        $dernierMessageIds = Message::query()
            ->selectRaw('MAX(id)')
            ->where(function (Builder $query) use ($utilisateurId): void {
                $query->where('expediteur_id', $utilisateurId)
                    ->orWhere('destinataire_id', $utilisateurId);
            })
            ->groupByRaw(
                'CASE WHEN expediteur_id = ? THEN destinataire_id ELSE expediteur_id END',
                [$utilisateurId],
            );

        $conversations = Message::query()
            ->with([
                'expediteur:id_utilisateur,nom,prenom,photo',
                'destinataire:id_utilisateur,nom,prenom,photo',
            ])
            ->whereIn('id', $dernierMessageIds)
            ->latest('id')
            ->get()
            ->map(function (Message $message) use ($utilisateurId): array {
                $contact = $message->expediteur_id === $utilisateurId
                    ? $message->destinataire
                    : $message->expediteur;

                return [
                    'contact' => $contact,
                    'dernier_message' => [
                        'contenu' => $message->contenu,
                        'created_at' => $message->created_at,
                        'expediteur_id' => $message->expediteur_id,
                    ],
                ];
            })
            ->values();

        return response()->json($conversations);
    }

    public function messages(Utilisateur $contact): JsonResponse
    {
        $utilisateurId = Auth::guard('utilisateur')->id();

        abort_if($contact->getKey() === $utilisateurId, 404);

        $messages = Message::query()
            ->where(function (Builder $query) use ($utilisateurId, $contact): void {
                $query->where('expediteur_id', $utilisateurId)
                    ->where('destinataire_id', $contact->getKey());
            })
            ->orWhere(function (Builder $query) use ($utilisateurId, $contact): void {
                $query->where('expediteur_id', $contact->getKey())
                    ->where('destinataire_id', $utilisateurId);
            })
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'contact' => $contact->only(['id_utilisateur', 'nom', 'prenom', 'photo']),
            'messages' => $messages,
        ]);
    }

    public function envoyer(Request $request, Utilisateur $contact): JsonResponse
    {
        $utilisateurId = Auth::guard('utilisateur')->id();
        abort_if($contact->getKey() === $utilisateurId, 404);

        $validated = $request->validate([
            'contenu' => ['required', 'string', 'max:2000'],
        ], [
            'contenu.required' => 'Écrivez un message avant de l’envoyer.',
            'contenu.max' => 'Votre message ne peut pas dépasser 2 000 caractères.',
        ]);

        $message = Message::create([
            'expediteur_id' => $utilisateurId,
            'destinataire_id' => $contact->getKey(),
            'contenu' => trim($validated['contenu']),
        ]);

        return response()->json($message, 201);
    }
}
