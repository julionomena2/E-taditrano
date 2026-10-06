@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <section class="stats-grid">
        <div class="stat-card accent">
            <span>Utilisateurs</span>
            <strong>{{ $stats['utilisateurs'] }}</strong>
        </div>
        <div class="stat-card">
            <span>Villes</span>
            <strong>{{ $stats['villes'] }}</strong>
        </div>
        <div class="stat-card">
            <span>Quartiers</span>
            <strong>{{ $stats['quartiers'] }}</strong>
        </div>
        <div class="stat-card">
            <span>Logements</span>
            <strong>{{ $stats['logements'] }}</strong>
        </div>
        <div class="stat-card">
            <span>Établissements</span>
            <strong>{{ $stats['etablissements'] }}</strong>
        </div>
    </section>

    <section class="two-col-grid">
        <div class="panel">
            <div class="panel-head">
                <h3>Derniers logements</h3>
                <a href="{{ route('admin.logements') }}" class="small-link">Voir tout</a>
            </div>

            <div class="list-stack">
                @forelse ($latestLogements as $logement)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $logement->type_logement }}</strong>
                            <small>{{ $logement->quartier?->nom_quartier ?? 'Quartier inconnu' }}</small>
                        </div>
                        <span>{{ $logement->prix }} €</span>
                    </div>
                @empty
                    <p class="empty-text">Aucun logement enregistré.</p>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h3>Derniers établissements</h3>
                <a href="{{ route('admin.etablissements') }}" class="small-link">Voir tout</a>
            </div>

            <div class="list-stack">
                @forelse ($latestEtablissements as $etablissement)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $etablissement->nom_etablissement }}</strong>
                            <small>{{ $etablissement->quartier?->nom_quartier ?? 'Quartier inconnu' }}</small>
                        </div>
                        <span>{{ $etablissement->latitude }}, {{ $etablissement->longitude }}</span>
                    </div>
                @empty
                    <p class="empty-text">Aucun établissement enregistré.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
