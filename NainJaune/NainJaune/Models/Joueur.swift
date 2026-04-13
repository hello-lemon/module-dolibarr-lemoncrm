import Foundation

/// Represente un joueur de la partie
struct Joueur: Identifiable, Equatable {
    let id: UUID
    var prenom: String
    var solde: Int
    var estElimine: Bool
    var estDonneur: Bool

    /// Points restants en main a la fin d'une manche (temporaire, reinitialise entre manches)
    var pointsRestants: Int = 0

    /// Cartes speciales encore en main a la fin de la manche
    var cartesNonJouees: Set<CarteSpeciale> = []

    init(prenom: String = "", solde: Int = 50) {
        self.id = UUID()
        self.prenom = prenom
        self.solde = solde
        self.estElimine = false
        self.estDonneur = false
    }

    /// Verifie si le joueur peut payer la mise de debut de manche
    var peutMiser: Bool {
        solde >= CarteSpeciale.totalMises
    }
}
