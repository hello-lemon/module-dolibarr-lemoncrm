import Foundation

/// Les differentes phases de la partie
enum EtatPartie {
    case accueil            // Ecran titre
    case configuration      // Saisie des joueurs et solde initial
    case attenteManche      // Dashboard, entre deux manches
    case mancheEnCours      // Pendant le jeu
    case finDeManche        // Saisie des resultats de fin de manche
    case partieTerminee     // Un joueur ne peut plus miser, partie finie
}
