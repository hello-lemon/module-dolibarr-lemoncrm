import Foundation

/// Une case du plateau avec son pot de jetons accumule
struct CasePlateau: Identifiable {
    let carte: CarteSpeciale
    var pot: Int

    var id: Int { carte.id }

    init(carte: CarteSpeciale) {
        self.carte = carte
        self.pot = 0
    }
}
