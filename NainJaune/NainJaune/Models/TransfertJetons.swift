import Foundation

/// Enregistre un transfert de jetons pour le recapitulatif de fin de manche
struct TransfertJetons: Identifiable {
    let id: UUID
    let source: String      // Nom du payeur (joueur ou "Case X")
    let destination: String  // Nom du receveur (joueur ou "Case X")
    let montant: Int
    let raison: TypeTransfert

    init(source: String, destination: String, montant: Int, raison: TypeTransfert) {
        self.id = UUID()
        self.source = source
        self.destination = destination
        self.montant = montant
        self.raison = raison
    }
}

/// Types de transferts possibles
enum TypeTransfert {
    case miseDebutManche       // Mise automatique de 15 jetons
    case gainCarteSpeciale     // Joueur a joue une carte speciale
    case paiementPointsMain    // Perdant paie au gagnant ses points restants
    case penaliteCarteNonJouee // Joueur remet des jetons sur la case
    case grandOpera            // Grand Opera : rafle toutes les cases
}
