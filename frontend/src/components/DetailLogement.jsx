import { useState } from "react";
import ItineraireLogement from "./ItineraireLogement.jsx";

export default function DetailLogement({ logement, etablissement, onRetour, onContacter }) {
  const [itineraireOuvert, setItineraireOuvert] = useState(false);
  const coordonneesDisponibles = logement.latitude !== null && logement.longitude !== null;

  return (
    <section className="detail-logement">
      <button className="lien-retour" type="button" onClick={onRetour}>
        ← Retour aux logements
      </button>

      <h1>{logement.type}</h1>
      <p className="prix">{logement.prix.toLocaleString("fr-FR")} Ar / mois</p>

      {logement.photos?.length > 0 && (
        <div className="detail-photos" aria-label="Photos du logement">
          {logement.photos.map((photo) => (
            <img key={photo.id ?? photo.chemin} src={photo.url} alt={`Photo du logement ${logement.type}`} />
          ))}
        </div>
      )}

      <div className="carte fiche-logement">
        {logement.quartier && <p><strong>Quartier :</strong> {logement.quartier.nom_quartier}</p>}
        {etablissement && logement.distanceKm !== null && (
          <p>
            <strong>Distance :</strong> {logement.distanceKm.toFixed(1)} km de {etablissement.nom}
          </p>
        )}
        {logement.description && (
          <p className="description-logement">
            <strong>Description :</strong> {logement.description}
          </p>
        )}
        {coordonneesDisponibles && (
          <p><strong>Coordonnées :</strong> {logement.latitude}, {logement.longitude}</p>
        )}
      </div>

      <div className="actions">
        <button
          type="button"
          onClick={() => setItineraireOuvert((ouvert) => !ouvert)}
          disabled={!coordonneesDisponibles}
        >
          {itineraireOuvert ? "Masquer l’itinéraire" : "Voir l’itinéraire"}
        </button>
        <button type="button" onClick={() => onContacter(logement.proprietaireId)}>
          Contacter le propriétaire
        </button>
      </div>

      {!coordonneesDisponibles && (
        <p className="itineraire-indisponible">
          L’itinéraire n’est pas disponible car cette annonce n’a pas de coordonnées enregistrées.
        </p>
      )}

      {itineraireOuvert && coordonneesDisponibles && (
        <ItineraireLogement latitude={logement.latitude} longitude={logement.longitude} />
      )}
    </section>
  );
}
