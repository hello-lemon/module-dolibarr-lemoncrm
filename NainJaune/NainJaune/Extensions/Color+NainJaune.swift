import SwiftUI

/// Palette de couleurs du Nain Jaune
/// Inspiree du tapis de jeu classique et de la boite traditionnelle
extension Color {

    // MARK: - Couleurs principales

    /// Vert fonce du tapis de jeu
    static let vertTapis = Color(light: .init(red: 0.106, green: 0.369, blue: 0.125),
                                  dark: .init(red: 0.180, green: 0.490, blue: 0.196))

    /// Bordeaux pour les accents et boutons principaux
    static let bordeaux = Color(light: .init(red: 0.482, green: 0.122, blue: 0.227),
                                 dark: .init(red: 0.663, green: 0.231, blue: 0.357))

    /// Or des jetons
    static let orJeton = Color(light: .init(red: 0.831, green: 0.659, blue: 0.263),
                                dark: .init(red: 1.0, green: 0.835, blue: 0.310))

    /// Creme des cartes et surfaces de saisie
    static let cremeCarte = Color(light: .init(red: 1.0, green: 0.973, blue: 0.906),
                                   dark: .init(red: 0.243, green: 0.208, blue: 0.161))

    /// Noir profond pour le texte principal
    static let noirProfond = Color(light: .init(red: 0.102, green: 0.102, blue: 0.102),
                                    dark: .init(red: 0.961, green: 0.961, blue: 0.961))

    // MARK: - Couleurs des cartes

    /// Rouge pour coeur et carreau
    static let rougeCarte = Color(light: .init(red: 0.776, green: 0.157, blue: 0.157),
                                   dark: .init(red: 0.937, green: 0.325, blue: 0.314))

    /// Noir pour trefle et pique
    static let noirCarte = Color(light: .init(red: 0.129, green: 0.129, blue: 0.129),
                                  dark: .init(red: 0.878, green: 0.878, blue: 0.878))

    // MARK: - Couleurs fonctionnelles

    /// Vert pour les gains
    static let vertReussite = Color(light: .init(red: 0.180, green: 0.490, blue: 0.196),
                                     dark: .init(red: 0.400, green: 0.733, blue: 0.416))

    /// Rouge pour les pertes et alertes
    static let rougeAlerte = Color(light: .init(red: 0.776, green: 0.157, blue: 0.157),
                                    dark: .init(red: 0.937, green: 0.325, blue: 0.314))

    /// Fond de l'application
    static let fondApp = Color(light: .init(red: 0.082, green: 0.290, blue: 0.098),
                                dark: .init(red: 0.078, green: 0.118, blue: 0.082))
}

// MARK: - Helper pour creer des couleurs adaptatives light/dark

extension Color {
    init(light: Color.Resolved, dark: Color.Resolved) {
        self.init { traits in
            traits.colorScheme == .dark ? Color(dark) : Color(light)
        }
    }
}
