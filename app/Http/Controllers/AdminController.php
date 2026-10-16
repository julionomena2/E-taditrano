<?php

namespace App\Http\Controllers;

use App\Models\Etablissement;
use App\Models\Logement;
use App\Models\Quartier;
use App\Models\Utilisateur;
use App\Models\Ville;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AdminController extends Controller
{
    private function ensureLoggedIn()
    {
        if (! session('admin_logged_in')) {
            redirect()->route('login')->send();
        }
    }

    public function dashboard()
    {
        $this->ensureLoggedIn();

        $stats = [
            'utilisateurs' => Utilisateur::count(),
            'villes' => Ville::count(),
            'quartiers' => Quartier::count(),
            'logements' => Logement::count(),
            'etablissements' => Etablissement::count(),
        ];

        $latestLogements = Logement::with(['utilisateur', 'quartier'])->latest('id')->take(5)->get();
        $latestEtablissements = Etablissement::with('quartier')->latest('id')->take(5)->get();

        return view('admin.dashboard', compact('stats', 'latestLogements', 'latestEtablissements'));
    }

    public function resource(string $model, Request $request)
    {
        $this->ensureLoggedIn();

        $quartiers = Quartier::with('ville')->get();
        $config = $this->resourceConfig($quartiers);

        abort_unless(array_key_exists($model, $config), 404);

        $records = $this->fetchRecords($model);
        $editing = null;

        if ($request->query('edit')) {
            $editing = $this->fetchRecords($model)->find($request->query('edit'));
        }

        $mapLocations = $model === 'etablissements'
            ? $quartiers->mapWithKeys(fn (Quartier $quartier) => [
                $quartier->getKey() => [
                    'quartier' => $quartier->nom_quartier,
                    'ville' => $quartier->ville?->nom_Ville,
                ],
            ])->all()
            : [];

        return view('admin.resource', [
            'model' => $model,
            'title' => $config[$model]['title'],
            'records' => $records,
            'fields' => $config[$model]['fields'],
            'editing' => $editing,
            'routeName' => $config[$model]['routeName'],
            'mapLocations' => $mapLocations,
        ]);
    }

    public function store(string $model, Request $request)
    {
        $this->ensureLoggedIn();

        $config = $this->resourceConfig();
        abort_unless(array_key_exists($model, $config), 404);

        $data = $request->validate($config[$model]['rules'] ?? $this->defaultRules($model));
        if ($model === 'utilisateurs' && $request->hasFile('photo')) {
            $path = $request->file('photo')->storePublicly('utilisateurs', 'public');
            if ($path === false) {
                throw new RuntimeException('La photo de profil n’a pas pu être enregistrée.');
            }

            $data['photo'] = $path;
        }

        $this->getModel($model)::create($data);

        return redirect()->route($config[$model]['routeName'])->with('success', 'Enregistrement ajouté avec succès.');
    }

    public function update(string $model, Request $request, $id)
    {
        $this->ensureLoggedIn();

        $config = $this->resourceConfig();
        abort_unless(array_key_exists($model, $config), 404);

        $record = $this->getModel($model)::findOrFail($id);
        $data = $request->validate($config[$model]['updateRules'] ?? $config[$model]['rules'] ?? $this->defaultRules($model));
        if ($model === 'utilisateurs' && blank($data['mot_de_passe'] ?? null)) {
            unset($data['mot_de_passe']);
        }

        $oldPhoto = null;
        if ($model === 'utilisateurs' && $request->hasFile('photo')) {
            $path = $request->file('photo')->storePublicly('utilisateurs', 'public');
            if ($path === false) {
                throw new RuntimeException('La photo de profil n’a pas pu être enregistrée.');
            }

            $oldPhoto = $record->photo;
            $data['photo'] = $path;
        }

        $record->update($data);
        if ($oldPhoto !== null) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return redirect()->route($config[$model]['routeName'])->with('success', 'Enregistrement mis à jour avec succès.');
    }

    public function destroy(string $model, $id)
    {
        $this->ensureLoggedIn();

        $config = $this->resourceConfig();
        abort_unless(array_key_exists($model, $config), 404);

        $record = $this->getModel($model)::findOrFail($id);
        $record->delete();
        if ($model === 'utilisateurs' && $record->photo !== null) {
            Storage::disk('public')->delete($record->photo);
        }

        return redirect()->route($config[$model]['routeName'])->with('success', 'Enregistrement supprimé avec succès.');
    }

    private function fetchRecords(string $model)
    {
        return match ($model) {
            'utilisateurs' => Utilisateur::orderBy('created_at', 'desc')->get(),
            'villes' => Ville::orderBy('created_at', 'desc')->get(),
            'quartiers' => Quartier::with('ville')->orderBy('created_at', 'desc')->get(),
            'logements' => Logement::with(['utilisateur', 'quartier'])->orderBy('created_at', 'desc')->get(),
            'etablissements' => Etablissement::with('quartier')->orderBy('created_at', 'desc')->get(),
            default => collect(),
        };
    }

    private function getModel(string $model)
    {
        return match ($model) {
            'utilisateurs' => Utilisateur::class,
            'villes' => Ville::class,
            'quartiers' => Quartier::class,
            'logements' => Logement::class,
            'etablissements' => Etablissement::class,
            default => null,
        };
    }

    private function resourceConfig(?Collection $quartiers = null): array
    {
        $quartierOptions = ($quartiers ?? Quartier::with('ville')->get())
            ->mapWithKeys(fn (Quartier $quartier) => [
                $quartier->getKey() => $quartier->nom_quartier.' — '.($quartier->ville?->nom_Ville ?? 'Ville inconnue'),
            ])
            ->all();

        return [
            'utilisateurs' => [
                'title' => 'Utilisateurs',
                'routeName' => 'admin.utilisateurs',
                'fields' => [
                    'id_utilisateur' => ['label' => 'ID utilisateur', 'type' => 'text', 'placeholder' => 'U001'],
                    'nom' => ['label' => 'Nom', 'type' => 'text', 'placeholder' => 'Nom'],
                    'prenom' => ['label' => 'Prénom', 'type' => 'text', 'placeholder' => 'Prénom'],
                    'mot_de_passe' => ['label' => 'Mot de passe', 'type' => 'password', 'placeholder' => '••••••'],
                    'telephone' => ['label' => 'Téléphone', 'type' => 'text', 'placeholder' => '0123456789'],
                    'photo' => ['label' => 'Photo de profil', 'type' => 'image'],
                ],
                'rules' => [
                    'id_utilisateur' => ['required', 'string', 'max:50', 'unique:utilisateurs,id_utilisateur'],
                    'nom' => ['required', 'string', 'max:100'],
                    'prenom' => ['required', 'string', 'max:100'],
                    'mot_de_passe' => ['required', 'string', 'min:8', 'max:128'],
                    'telephone' => ['required', 'string', 'max:10'],
                    'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                ],
                'updateRules' => [
                    'nom' => ['sometimes', 'string', 'max:100'],
                    'prenom' => ['sometimes', 'string', 'max:100'],
                    'mot_de_passe' => ['sometimes', 'nullable', 'string', 'min:8', 'max:128'],
                    'telephone' => ['sometimes', 'string', 'max:10'],
                    'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                ],
            ],
            'villes' => [
                'title' => 'Villes',
                'routeName' => 'admin.villes',
                'fields' => [
                    'id_ville' => ['label' => 'ID ville', 'type' => 'text', 'placeholder' => 'DAK'],
                    'nom_Ville' => ['label' => 'Nom de la ville', 'type' => 'text', 'placeholder' => 'Nom ville'],
                ],
                'rules' => [
                    'id_ville' => ['required', 'string', 'max:5', 'unique:villes,id_ville'],
                    'nom_Ville' => ['required', 'string', 'max:100'],
                ],
                'updateRules' => [
                    'nom_Ville' => ['sometimes', 'string', 'max:100'],
                ],
            ],
            'quartiers' => [
                'title' => 'Quartiers',
                'routeName' => 'admin.quartiers',
                'fields' => [
                    'id_quartier' => ['label' => 'ID quartier', 'type' => 'text', 'placeholder' => 'Q001'],
                    'nom_quartier' => ['label' => 'Nom quartier', 'type' => 'text', 'placeholder' => 'Nom quartier'],
                    'id_ville' => ['label' => 'Ville', 'type' => 'select', 'options' => Ville::pluck('nom_Ville', 'id_ville')->toArray()],
                ],
                'rules' => [
                    'id_quartier' => ['required', 'string', 'max:20', 'unique:quartiers,id_quartier'],
                    'nom_quartier' => ['required', 'string', 'max:100'],
                    'id_ville' => ['required', 'string', 'exists:villes,id_ville'],
                ],
                'updateRules' => [
                    'nom_quartier' => ['sometimes', 'string', 'max:100'],
                    'id_ville' => ['sometimes', 'string', 'exists:villes,id_ville'],
                ],
            ],
            'logements' => [
                'title' => 'Logements',
                'routeName' => 'admin.logements',
                'fields' => [
                    'id_utilisateur' => ['label' => 'Utilisateur', 'type' => 'select', 'options' => Utilisateur::pluck('id_utilisateur', 'id_utilisateur')->toArray()],
                    'id_quartier' => ['label' => 'Quartier — ville', 'type' => 'select', 'options' => $quartierOptions],
                    'description' => ['label' => 'Description', 'type' => 'textarea', 'placeholder' => 'Description du logement'],
                    'type_logement' => ['label' => 'Type', 'type' => 'text', 'placeholder' => 'Appartement'],
                    'prix' => ['label' => 'Prix', 'type' => 'number', 'placeholder' => '450000'],
                    'date_publication' => ['label' => 'Date publication', 'type' => 'date'],
                    'status' => ['label' => 'Statut', 'type' => 'text', 'placeholder' => 'Disponible'],
                    'photo' => ['label' => 'Photo', 'type' => 'text', 'placeholder' => 'nom_fichier.jpg'],
                    'latitude' => ['label' => 'Latitude', 'type' => 'number', 'step' => 'any'],
                    'longitude' => ['label' => 'Longitude', 'type' => 'number', 'step' => 'any'],
                ],
                'rules' => [
                    'id_utilisateur' => ['required', 'string', 'exists:utilisateurs,id_utilisateur'],
                    'id_quartier' => ['required', 'string', 'exists:quartiers,id_quartier'],
                    'description' => ['required', 'string'],
                    'type_logement' => ['required', 'string', 'max:100'],
                    'prix' => ['required', 'integer', 'min:0'],
                    'date_publication' => ['required', 'date'],
                    'status' => ['required', 'string', 'max:100'],
                    'photo' => ['nullable', 'string', 'max:1000'],
                    'latitude' => ['nullable', 'numeric'],
                    'longitude' => ['nullable', 'numeric'],
                ],
                'updateRules' => [
                    'id_utilisateur' => ['sometimes', 'string', 'exists:utilisateurs,id_utilisateur'],
                    'id_quartier' => ['sometimes', 'string', 'exists:quartiers,id_quartier'],
                    'description' => ['sometimes', 'string'],
                    'type_logement' => ['sometimes', 'string', 'max:100'],
                    'prix' => ['sometimes', 'integer', 'min:0'],
                    'date_publication' => ['sometimes', 'date'],
                    'status' => ['sometimes', 'string', 'max:100'],
                    'photo' => ['nullable', 'string', 'max:1000'],
                    'latitude' => ['nullable', 'numeric'],
                    'longitude' => ['nullable', 'numeric'],
                ],
            ],
            'etablissements' => [
                'title' => 'Établissements',
                'routeName' => 'admin.etablissements',
                'fields' => [
                    'nom_etablissement' => ['label' => 'Nom établissement', 'type' => 'text', 'placeholder' => 'École, Hôpital...'],
                    'id_quartier' => ['label' => 'Quartier — ville', 'type' => 'select', 'options' => $quartierOptions],
                    'latitude' => ['label' => 'Latitude', 'type' => 'number', 'step' => 'any'],
                    'longitude' => ['label' => 'Longitude', 'type' => 'number', 'step' => 'any'],
                ],
                'rules' => [
                    'nom_etablissement' => ['required', 'string', 'max:100'],
                    'id_quartier' => ['required', 'string', 'exists:quartiers,id_quartier'],
                    'latitude' => ['required', 'numeric', 'between:-90,90'],
                    'longitude' => ['required', 'numeric', 'between:-180,180'],
                ],
                'updateRules' => [
                    'nom_etablissement' => ['sometimes', 'string', 'max:100'],
                    'id_quartier' => ['sometimes', 'string', 'exists:quartiers,id_quartier'],
                    'latitude' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
                    'longitude' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
                ],
            ],
        ];
    }

    private function defaultRules(string $model): array
    {
        return [
            'name' => ['required', 'string'],
        ];
    }
}
