import { useEffect, useRef, useState } from "react";
import { obtenirBibliothequesGoogleMaps } from "../services/googleMaps.js";

function messageErreurGeolocalisation(code) {
  if (code === 1) {
    return "L’accès à votre position a été refusé. Autorisez la géolocalisation dans les paramètres du navigateur.";
  }

  if (code === 2) {
    return "Votre position est indisponible. Vérifiez que la localisation est activée sur votre appareil.";
  }

  if (code === 3) {
    return "La recherche de votre position a expiré. Réessayez dans un instant.";
  }

  return "Votre position n’a pas pu être déterminée.";
}

export default function ItineraireLogement({ latitude, longitude }) {
  const conteneurCarte = useRef(null);
  const conteneurEtapes = useRef(null);
  const afficheurItineraire = useRef(null);
  const [positionOrigine, setPositionOrigine] = useState(null);
  const [demandeEnCours, setDemandeEnCours] = useState(false);
  const [resume, setResume] = useState(null);
  const [erreur, setErreur] = useState("");
  const [erreurCalculItineraire, setErreurCalculItineraire] = useState(false);
  const calculEnCours = Boolean(positionOrigine && !resume && !erreur);
  const urlItineraireGoogle = positionOrigine
    ? `https://www.google.com/maps/dir/?api=1&origin=${positionOrigine.lat},${positionOrigine.lng}&destination=${latitude},${longitude}&travelmode=driving`
    : null;

  function localiserUtilisateur() {
    if (!window.isSecureContext) {
      setErreur("La géolocalisation exige une connexion HTTPS (ou localhost en développement).");
      return;
    }

    if (!navigator.geolocation) {
      setErreur("La géolocalisation n’est pas disponible dans ce navigateur.");
      return;
    }

    setErreur("");
    setResume(null);
    setErreurCalculItineraire(false);
    setDemandeEnCours(true);

    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        setPositionOrigine({
          lat: coords.latitude,
          lng: coords.longitude,
        });
        setDemandeEnCours(false);
      },
      (geolocalisationErreur) => {
        setErreur(messageErreurGeolocalisation(geolocalisationErreur.code));
        setDemandeEnCours(false);
      },
      {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 60000,
      },
    );
  }

  useEffect(() => {
    if (!positionOrigine) {
      return undefined;
    }

    let annule = false;

    obtenirBibliothequesGoogleMaps()
      .then(({ Carte, ServiceItineraire, AfficheurItineraire, ModesDeplacement }) => {
        if (annule || !conteneurCarte.current) {
          return;
        }

        const carte = new Carte(conteneurCarte.current, {
          center: positionOrigine,
          zoom: 12,
          mapTypeControl: false,
          streetViewControl: false,
          fullscreenControl: true,
        });
        const afficheur = new AfficheurItineraire({
          map: carte,
          panel: conteneurEtapes.current,
          suppressMarkers: false,
        });
        const service = new ServiceItineraire();
        afficheurItineraire.current = afficheur;

        return new Promise((resolve, reject) => {
          service.route({
            origin: positionOrigine,
            destination: { lat: latitude, lng: longitude },
            travelMode: ModesDeplacement.DRIVING,
            provideRouteAlternatives: false,
          }, (resultat, statut) => {
            if (statut === "OK" && resultat) {
              resolve(resultat);
              return;
            }

            const erreurStatut = new Error(statut || "UNKNOWN_ERROR");
            erreurStatut.status = statut;
            reject(erreurStatut);
          });
        }).then((resultat) => {
          if (annule) {
            return;
          }

          const trajet = resultat.routes[0]?.legs[0];
          if (!trajet) {
            setErreur("Aucun itinéraire n’a été trouvé entre votre position et ce logement.");
            return;
          }

          afficheur.setDirections(resultat);
          setResume({
            distance: trajet.distance?.text,
            duree: trajet.duration?.text,
            depart: trajet.start_address,
            arrivee: trajet.end_address,
          });
        });
      })
      .catch((erreurItineraire) => {
        if (annule) {
          return;
        }

        const detailsErreur = [
          erreurItineraire.code,
          erreurItineraire.status,
          erreurItineraire.message,
        ].filter(Boolean).join(" ");
        const statut = erreurItineraire.status || erreurItineraire.message;
        const accesRefuse = statut === "REQUEST_DENIED"
          || /not authorized|not enabled|PERMISSION_DENIED/i.test(detailsErreur);
        setErreurCalculItineraire(accesRefuse || statut === "OVER_QUERY_LIMIT");

        if (statut === "REQUEST_DENIED") {
          setErreur(
            "Google renvoie REQUEST_DENIED : la page n’est pas autorisée à utiliser Directions. Vérifiez les référents HTTP autorisés et que Directions API (Legacy) est incluse dans les restrictions de cette clé.",
          );
        } else if (statut === "OVER_QUERY_LIMIT") {
          setErreur(
            "Google renvoie OVER_QUERY_LIMIT : vérifiez les quotas Directions API et la facturation du projet Google Cloud.",
          );
        } else if (statut === "ZERO_RESULTS") {
          setErreur(
            "Google renvoie ZERO_RESULTS : aucun itinéraire routier n’est disponible entre ces deux positions.",
          );
        } else if (statut === "NOT_FOUND") {
          setErreur("Google renvoie NOT_FOUND : l’origine ou la destination n’a pas pu être localisée.");
        } else if (statut === "INVALID_REQUEST") {
          setErreur("Google renvoie INVALID_REQUEST : les coordonnées ou les options de l’itinéraire sont invalides.");
        } else {
          setErreur(`Le calcul d’itinéraire a échoué${statut ? ` (statut Google : ${statut})` : ""}. Vérifiez la configuration Google Maps puis réessayez.`);
        }
      });

    return () => {
      annule = true;
      afficheurItineraire.current?.setMap(null);
      afficheurItineraire.current = null;
    };
  }, [latitude, longitude, positionOrigine]);

  return (
    <section className="itineraire-logement" aria-labelledby="itineraire-titre">
      <div className="itineraire-entete">
        <div>
          <h2 id="itineraire-titre">Itinéraire depuis ma position</h2>
          <p>Le navigateur demandera l’autorisation d’accéder à votre localisation.</p>
        </div>
        <button
          className="annonce-bouton-principal"
          type="button"
          onClick={localiserUtilisateur}
          disabled={demandeEnCours || calculEnCours}
        >
          {demandeEnCours
            ? "Recherche de ma position..."
            : calculEnCours
              ? "Calcul de l’itinéraire..."
              : positionOrigine
                ? "Actualiser l’itinéraire"
                : "Utiliser ma position"}
        </button>
      </div>

      {erreur && (
        <div className="itineraire-erreur" role="alert">
          <p className="message-erreur">{erreur}</p>
          {erreurCalculItineraire && urlItineraireGoogle && (
            <>
              <a
                className="itineraire-lien-secours"
                href={urlItineraireGoogle}
                target="_blank"
                rel="noreferrer"
              >
                Ouvrir l’itinéraire dans Google Maps
              </a>
              {erreur.includes("REQUEST_DENIED") && (
                <p className="itineraire-aide-referents">
                  Dans Google Cloud → Identifiants → cette clé, autorisez l’origine réellement utilisée par l’application, par exemple
                  {" "}
                  <code>http://localhost:5173/*</code>, <code>http://127.0.0.1:5173/*</code>
                  {" "}ou <code>http://localhost:8000/*</code>, puis autorisez Directions API (Legacy) dans les restrictions d’API.
                </p>
              )}
            </>
          )}
        </div>
      )}

      {positionOrigine && (
        <div className="itineraire-resultat">
          {resume && (
            <div className="itineraire-resume" aria-live="polite">
              {resume.distance && <strong>{resume.distance}</strong>}
              {resume.duree && <span>En voiture · environ {resume.duree}</span>}
            </div>
          )}
          <div
            className="itineraire-carte"
            ref={conteneurCarte}
            role="application"
            aria-label="Carte de l’itinéraire en voiture"
          />
          <div className="itineraire-etapes" ref={conteneurEtapes} aria-label="Étapes de l’itinéraire" />
          {resume?.depart && (
            <p className="itineraire-adresses">
              Départ : {resume.depart}<br />
              Arrivée : {resume.arrivee}
            </p>
          )}
        </div>
      )}
    </section>
  );
}
