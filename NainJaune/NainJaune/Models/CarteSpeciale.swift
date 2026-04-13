import SwiftUI

/// Les 5 cartes speciales du Nain Jaune et leurs mises associees
/// Le rawValue correspond directement au montant de la mise
enum CarteSpeciale: Int, CaseIterable, Identifiable, Codable, Hashable {
    case dixDeCarreau = 1      // mise de 1
    case valetDeTrefle = 2     // mise de 2
    case dameDePique = 3       // mise de 3
    case roiDeCoeur = 4        // mise de 4
    case septDeCarreau = 5     // mise de 5 (le Nain Jaune)

    var id: Int { rawValue }

    /// Mise requise a chaque debut de manche
    var mise: Int { rawValue }

    /// Total des mises pour les 5 cases (1+2+3+4+5)
    static var totalMises: Int { 15 }

    /// Nom affiche en francais
    var nom: String {
        switch self {
        case .dixDeCarreau: "10 de Carreau"
        case .valetDeTrefle: "Valet de Trefle"
        case .dameDePique: "Dame de Pique"
        case .roiDeCoeur: "Roi de Coeur"
        case .septDeCarreau: "7 de Carreau"
        }
    }

    /// Nom court pour affichage compact
    var nomCourt: String {
        switch self {
        case .dixDeCarreau: "10\u{2666}"
        case .valetDeTrefle: "V\u{2663}"
        case .dameDePique: "D\u{2660}"
        case .roiDeCoeur: "R\u{2665}"
        case .septDeCarreau: "7\u{2666}"
        }
    }

    /// Symbole Unicode de la couleur
    var symbole: String {
        switch self {
        case .dixDeCarreau, .septDeCarreau: "\u{2666}" // carreau
        case .valetDeTrefle: "\u{2663}" // trefle
        case .dameDePique: "\u{2660}" // pique
        case .roiDeCoeur: "\u{2665}" // coeur
        }
    }

    /// Couleur associee (rouge pour coeur/carreau, noir pour trefle/pique)
    var couleurCarte: Color {
        switch self {
        case .dixDeCarreau, .septDeCarreau, .roiDeCoeur:
            return .rougeCarte
        case .valetDeTrefle, .dameDePique:
            return .noirCarte
        }
    }

    /// Nom de la figure sur la carte
    var figure: String {
        switch self {
        case .dixDeCarreau: "10"
        case .valetDeTrefle: "V"
        case .dameDePique: "D"
        case .roiDeCoeur: "R"
        case .septDeCarreau: "7"
        }
    }
}
