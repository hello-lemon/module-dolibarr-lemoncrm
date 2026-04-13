import UIKit

/// Gestion centralisee des retours haptiques
enum HaptiqueManager {
    /// Gain de jetons, carte speciale jouee
    static func succes() {
        let generateur = UINotificationFeedbackGenerator()
        generateur.notificationOccurred(.success)
    }

    /// Penalite, perte de jetons
    static func avertissement() {
        let generateur = UINotificationFeedbackGenerator()
        generateur.notificationOccurred(.warning)
    }

    /// Elimination, fin de partie
    static func erreur() {
        let generateur = UINotificationFeedbackGenerator()
        generateur.notificationOccurred(.error)
    }

    /// Selection d'un element (bouton, joueur)
    static func selection() {
        let generateur = UISelectionFeedbackGenerator()
        generateur.selectionChanged()
    }

    /// Impact leger (transition d'ecran)
    static func impactLeger() {
        let generateur = UIImpactFeedbackGenerator(style: .light)
        generateur.impactOccurred()
    }

    /// Impact moyen (nouvelle manche)
    static func impactMoyen() {
        let generateur = UIImpactFeedbackGenerator(style: .medium)
        generateur.impactOccurred()
    }
}
