import { useEffect, useRef, useState } from "react";
import { obtenirBibliothequesGoogleMaps } from "../services/googleMaps.js";

const CENTRE_MADAGASCAR = { lat: -18.8792, lng: 47.5079 };

export default function GoogleMapPicker({
  ville,
  quartier,
  latitude,
  longitude,
  onCoordonneesChange,
}) {
  const conteneurCarte = useRef(null);
  const carte = useRef(null);
  const marqueur = useRef(null);
  const geocodeur = useRef(null);
  const dernierGeocodage = useRef(0);
  const callbackCoordonnees = useRef(onCoordonneesChange);
  const [bibliothequesChargees, setBibliothequesChargees] = useState(false);
  const [vueSatellite, setVueSatellite] = useState(false);
  const [erreurChargement, setErreurChargement] = useState("");
  const [resultatGeocodage, setResultatGeocodage] = useState(null);

  useEffect(() => {
    callbackCoordonnees.current = onCoordonneesChange;
  }, [onCoordonneesChange]);

  useEffect(() => {
    let annule = false;
    let ecouteurClic;

    obtenirBibliothequesGoogleMaps()
      .then(({ Carte, Geocodeur, Marqueur }) => {
        if (annule || !conteneurCarte.current) {
          return;
        }

        carte.current = new Carte(conteneurCarte.current, {
          center: CENTRE_MADAGASCAR,
          zoom: 12,
          mapTypeId: "roadmap",
          mapTypeControl: false,
          streetViewControl: false,
          fullscreenControl: true,
        });
        geocodeur.current = new Geocodeur();
        marqueur.current = new Marqueur({ map: carte.current });
        ecouteurClic = carte.current.addListener("click", (evenement) => {
          if (!evenement.latLng) {
            return;
          }

          dernierGeocodage.current++;
          const coordonnees = {
            latitude: evenement.latLng.lat(),
            longitude: evenement.latLng.lng(),
          };
          marqueur.current.setPosition({
            lat: coordonnees.latitude,
            lng: coordonnees.longitude,
          });
          callbackCoordonnees.current(coordonnees);
        });
        setBibliothequesChargees(true);
      })
      .catch((erreur) => {
        if (!annule) {
          setErreurChargement(erreur.message || "Google Maps n’a pas pu être chargé.");
        }
      });

    return () => {
      annule = true;
      ecouteurClic?.remove();
      marqueur.current?.setMap(null);
      carte.current = null;
      marqueur.current = null;
      geocodeur.current = null;
    };
  }, []);

  function basculerVueCarte() {
    const prochaineVueSatellite = !vueSatellite;
    setVueSatellite(prochaineVueSatellite);
    carte.current?.setMapTypeId(prochaineVueSatellite ? "satellite" : "roadmap");
  }

  useEffect(() => {
    if (!bibliothequesChargees || !carte.current) {
      return;
    }

    const coordonneesExistantes = Number.isFinite(latitude) && Number.isFinite(longitude);
    if (coordonneesExistantes) {
      dernierGeocodage.current++;
      const position = { lat: latitude, lng: longitude };
      carte.current.setCenter(position);
      carte.current.setZoom(17);
      marqueur.current.setPosition(position);
      return;
    }

    if (!ville || !quartier) {
      return;
    }

    const requete = ++dernierGeocodage.current;
    const cleRecherche = `${quartier}, ${ville}`;
    marqueur.current.setPosition(null);

    async function centrerCarteSurLocalisation() {
      try {
        const resultatQuartier = await geocodeur.current.geocode({
          address: `${quartier}, ${ville}, Madagascar`,
          componentRestrictions: { country: "MG" },
          region: "MG",
        });

        if (annule || requete !== dernierGeocodage.current) {
          return;
        }

        const localisationQuartier = resultatQuartier.results[0];
        if (localisationQuartier) {
          carte.current.setCenter(localisationQuartier.geometry.location);
          carte.current.setZoom(15);
          setResultatGeocodage({
            cleRecherche,
            message: `Carte centrée sur ${quartier}, ${ville}. Cliquez pour placer le repère.`,
          });
          return;
        }

        const resultatVille = await geocodeur.current.geocode({
          address: `${ville}, Madagascar`,
          componentRestrictions: { country: "MG" },
          region: "MG",
        });

        if (annule || requete !== dernierGeocodage.current) {
          return;
        }

        const localisationVille = resultatVille.results[0];
        if (!localisationVille) {
          setResultatGeocodage({
            cleRecherche,
            message: `La ville ${ville} n’a pas été trouvée. Vérifiez la configuration du géocodage Google.`,
          });
          return;
        }

        carte.current.setCenter(localisationVille.geometry.location);
        carte.current.setZoom(12);
        setResultatGeocodage({
          cleRecherche,
          message: `Quartier introuvable : carte centrée sur ${ville}. Zoomez sur ${quartier} avant de placer le repère.`,
        });
      } catch {
        if (!annule && requete === dernierGeocodage.current) {
          setResultatGeocodage({
            cleRecherche,
            message: "Le quartier n’a pas pu être géolocalisé. Vérifiez que Geocoding API est activée et autorisée pour cette clé.",
          });
        }
      }
    }

    centrerCarteSurLocalisation();
  }, [bibliothequesChargees, latitude, longitude, quartier, ville]);

  const cleRechercheCourante = quartier && ville ? `${quartier}, ${ville}` : "";
  const etatCarte = erreurChargement
    || (Number.isFinite(latitude) && Number.isFinite(longitude)
      ? "Emplacement sélectionné. Cliquez sur la carte pour le déplacer."
      : !bibliothequesChargees
        ? "Chargement de Google Maps..."
        : !ville || !quartier
          ? "Choisissez une ville et un quartier pour centrer la carte."
          : resultatGeocodage?.cleRecherche === cleRechercheCourante
            ? resultatGeocodage.message
            : "Recherche du quartier sur la carte...");

  return (
    <div className="annonce-carte-groupe">
      <div className="annonce-carte-conteneur">
        <div
          className="annonce-carte"
          ref={conteneurCarte}
          role="application"
          aria-label="Carte Google Maps. Cliquez pour placer le repère du logement."
        />
        <button
          className="annonce-carte-bascule"
          type="button"
          onClick={basculerVueCarte}
          disabled={!bibliothequesChargees}
          aria-label={`Passer à la vue ${vueSatellite ? "standard" : "satellite"}`}
        >
          {vueSatellite ? "Vue standard" : "Vue satellite"}
        </button>
      </div>
      <p className="annonce-carte-etat" role="status">{etatCarte}</p>
    </div>
  );
}
