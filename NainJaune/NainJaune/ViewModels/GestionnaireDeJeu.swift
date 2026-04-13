import SwiftUI

/// Gestionnaire principal du jeu - source de verite unique
/// Contient toute la logique metier du Nain Jaune
@Observable
final class GestionnaireDeJeu {

    // MARK: - Etat de la partie

    var etat: EtatPartie = .accueil
    var joueurs: [Joueur] = []
    var cases: [CasePlateau] = CarteSpeciale.allCases.map { CasePlateau(carte: $0) }
    var soldeInitial: Int = 50
    var mancheNumero: Int = 0
    var indexDonneur: Int = 0

    // MARK: - Etat temporaire de la manche en cours

    /// Cartes speciales deja jouees pendant cette manche (carte -> id du joueur)
    var cartesJoueesCetteManche: [CarteSpeciale: UUID] = [:]

    /// Transferts de la manche en cours (pour le recapitulatif)
    var transfertsManche: [TransfertJetons] = []

    /// Gagnant selectionne en fin de manche
    var gagnantId: UUID? = nil

    /// Flag Grand Opera
    var estGrandOpera: Bool = false

    /// Message d'erreur a afficher
    var messageErreur: String? = nil

    // MARK: - Proprietes calculees

    /// Joueurs encore en jeu (non elimines)
    var joueursActifs: [Joueur] {
        joueurs.filter { !$0.estElimine }
    }

    /// Le joueur gagnant selectionne
    var gagnant: Joueur? {
        guard let gagnantId else { return nil }
        return joueurs.first { $0.id == gagnantId }
    }

    /// Le donneur actuel
    var donneur: Joueur? {
        guard !joueursActifs.isEmpty else { return nil }
        let index = indexDonneur % joueursActifs.count
        return joueursActifs[index]
    }

    /// Cartes non jouees cette manche (disponibles pour declaration de penalite)
    var cartesNonJoueesCetteManche: [CarteSpeciale] {
        CarteSpeciale.allCases.filter { cartesJoueesCetteManche[$0] == nil }
    }

    /// Total des pots sur toutes les cases
    var totalPots: Int {
        cases.reduce(0) { $0 + $1.pot }
    }

    /// Verifie si tous les perdants ont saisi leurs points
    var tousLesPointsSaisis: Bool {
        guard let gagnantId else { return false }
        return joueurs
            .filter { $0.id != gagnantId && !$0.estElimine }
            .allSatisfy { $0.pointsRestants > 0 || $0.cartesNonJouees.isEmpty == false || true }
    }

    // MARK: - Configuration

    /// Ajoute un joueur avec un prenom vide
    func ajouterJoueur() {
        guard joueurs.count < 8 else { return }
        joueurs.append(Joueur(solde: soldeInitial))
    }

    /// Supprime un joueur par son id
    func supprimerJoueur(id: UUID) {
        guard joueurs.count > 3 else { return }
        joueurs.removeAll { $0.id == id }
    }

    /// Verifie si la partie peut demarrer
    var peutDemarrer: Bool {
        joueurs.count >= 3 &&
        joueurs.count <= 8 &&
        joueurs.allSatisfy { !$0.prenom.trimmingCharacters(in: .whitespaces).isEmpty }
    }

    /// Demarre la partie avec la configuration actuelle
    func demarrerPartie() {
        guard peutDemarrer else { return }

        // Appliquer le solde initial a tous les joueurs
        for index in joueurs.indices {
            joueurs[index].solde = soldeInitial
            joueurs[index].estElimine = false
            joueurs[index].estDonneur = false
        }

        // Le premier joueur est le premier donneur
        if !joueurs.isEmpty {
            joueurs[0].estDonneur = true
        }

        mancheNumero = 0
        indexDonneur = 0
        cases = CarteSpeciale.allCases.map { CasePlateau(carte: $0) }

        withAnimation(.easeInOut(duration: 0.4)) {
            etat = .attenteManche
        }
    }

    // MARK: - Debut de manche

    /// Demarre une nouvelle manche : preleve les mises et alimente les cases
    /// Retourne false si un joueur ne peut pas payer (fin de partie)
    @discardableResult
    func demarrerNouvelleManche() -> Bool {
        // Verifier que tous les joueurs actifs peuvent payer
        for joueur in joueursActifs {
            if !joueur.peutMiser {
                // Ce joueur ne peut plus payer -> fin de partie
                terminerPartie()
                return false
            }
        }

        mancheNumero += 1
        cartesJoueesCetteManche = [:]
        transfertsManche = []
        gagnantId = nil
        estGrandOpera = false

        // Reinitialiser les donnees temporaires de chaque joueur
        for index in joueurs.indices {
            joueurs[index].pointsRestants = 0
            joueurs[index].cartesNonJouees = []
        }

        // Prelever les mises et alimenter les cases
        for index in joueurs.indices where !joueurs[index].estElimine {
            joueurs[index].solde -= CarteSpeciale.totalMises

            transfertsManche.append(TransfertJetons(
                source: joueurs[index].prenom,
                destination: "Plateau",
                montant: CarteSpeciale.totalMises,
                raison: .miseDebutManche
            ))
        }

        // Chaque joueur actif alimente chaque case
        let nombreJoueursActifs = joueursActifs.count
        for index in cases.indices {
            cases[index].pot += cases[index].carte.mise * nombreJoueursActifs
        }

        // Avancer le donneur
        avancerDonneur()

        HaptiqueManager.impactMoyen()

        withAnimation(.easeInOut(duration: 0.4)) {
            etat = .mancheEnCours
        }

        return true
    }

    // MARK: - Pendant la manche

    /// Declare qu'un joueur a joue une carte speciale -> il recupere le pot
    func declarerCarteJouee(carte: CarteSpeciale, parJoueur joueurId: UUID) {
        guard cartesJoueesCetteManche[carte] == nil else { return }
        guard let joueurIndex = joueurs.firstIndex(where: { $0.id == joueurId }) else { return }
        guard let caseIndex = cases.firstIndex(where: { $0.carte == carte }) else { return }

        let montant = cases[caseIndex].pot

        // Transferer le pot au joueur
        joueurs[joueurIndex].solde += montant
        cases[caseIndex].pot = 0
        cartesJoueesCetteManche[carte] = joueurId

        transfertsManche.append(TransfertJetons(
            source: carte.nom,
            destination: joueurs[joueurIndex].prenom,
            montant: montant,
            raison: .gainCarteSpeciale
        ))

        HaptiqueManager.succes()
    }

    // MARK: - Fin de manche

    /// Selectionne le gagnant de la manche
    func selectionnerGagnant(joueurId: UUID) {
        gagnantId = joueurId
        estGrandOpera = false
        HaptiqueManager.selection()
    }

    /// Saisit les points restants en main d'un perdant
    func saisirPointsRestants(joueurId: UUID, points: Int) {
        guard let index = joueurs.firstIndex(where: { $0.id == joueurId }) else { return }
        joueurs[index].pointsRestants = max(0, points)
    }

    /// Declare qu'un joueur a encore une carte speciale en main (penalite)
    func declarerCarteNonJouee(carte: CarteSpeciale, joueurId: UUID) {
        guard let index = joueurs.firstIndex(where: { $0.id == joueurId }) else { return }
        joueurs[index].cartesNonJouees.insert(carte)
    }

    /// Retire la declaration d'une carte non jouee
    func annulerCarteNonJouee(carte: CarteSpeciale, joueurId: UUID) {
        guard let index = joueurs.firstIndex(where: { $0.id == joueurId }) else { return }
        joueurs[index].cartesNonJouees.remove(carte)
    }

    /// Active/desactive le Grand Opera
    func basculerGrandOpera() {
        estGrandOpera.toggle()
    }

    /// Valide et applique tous les transferts de fin de manche
    func validerFinDeManche() {
        guard let gagnantId else { return }
        guard let gagnantIndex = joueurs.firstIndex(where: { $0.id == gagnantId }) else { return }

        // Reinitialiser les transferts pour ne garder que ceux de fin de manche
        var transfertsFinManche: [TransfertJetons] = []

        if estGrandOpera {
            // Grand Opera : le gagnant rafle TOUTES les cases
            for index in cases.indices {
                let montant = cases[index].pot
                if montant > 0 {
                    joueurs[gagnantIndex].solde += montant
                    transfertsFinManche.append(TransfertJetons(
                        source: cases[index].carte.nom,
                        destination: joueurs[gagnantIndex].prenom,
                        montant: montant,
                        raison: .grandOpera
                    ))
                    cases[index].pot = 0
                }
            }
        }

        // Chaque perdant paie ses points restants au gagnant
        for index in joueurs.indices {
            guard joueurs[index].id != gagnantId && !joueurs[index].estElimine else { continue }

            let points = joueurs[index].pointsRestants
            if points > 0 {
                joueurs[index].solde -= points
                joueurs[gagnantIndex].solde += points
                transfertsFinManche.append(TransfertJetons(
                    source: joueurs[index].prenom,
                    destination: joueurs[gagnantIndex].prenom,
                    montant: points,
                    raison: .paiementPointsMain
                ))
            }
        }

        // Penalites pour cartes speciales non jouees (sauf en Grand Opera ou les cases sont deja videes)
        for index in joueurs.indices {
            guard joueurs[index].id != gagnantId && !joueurs[index].estElimine else { continue }

            for carte in joueurs[index].cartesNonJouees {
                guard let caseIndex = cases.firstIndex(where: { $0.carte == carte }) else { continue }

                let penalite = cases[caseIndex].pot
                if penalite > 0 {
                    joueurs[index].solde -= penalite
                    cases[caseIndex].pot += penalite // Double le pot
                    transfertsFinManche.append(TransfertJetons(
                        source: joueurs[index].prenom,
                        destination: carte.nom,
                        montant: penalite,
                        raison: .penaliteCarteNonJouee
                    ))
                }
            }
        }

        transfertsManche.append(contentsOf: transfertsFinManche)

        // Reinitialiser les donnees temporaires
        for index in joueurs.indices {
            joueurs[index].pointsRestants = 0
            joueurs[index].cartesNonJouees = []
        }

        HaptiqueManager.succes()

        withAnimation(.easeInOut(duration: 0.4)) {
            etat = .attenteManche
        }
    }

    /// Passe a l'ecran de fin de manche
    func demanderFinDeManche() {
        withAnimation(.easeInOut(duration: 0.4)) {
            etat = .finDeManche
        }
    }

    // MARK: - Fin de partie

    /// Termine la partie (un joueur ne peut plus miser)
    private func terminerPartie() {
        HaptiqueManager.erreur()
        withAnimation(.easeInOut(duration: 0.4)) {
            etat = .partieTerminee
        }
    }

    /// Classement des joueurs par solde decroissant
    func classement() -> [Joueur] {
        joueurs.sorted { $0.solde > $1.solde }
    }

    /// Recommencer avec les memes joueurs et les soldes reinitialises
    func recommencer() {
        for index in joueurs.indices {
            joueurs[index].solde = soldeInitial
            joueurs[index].estElimine = false
            joueurs[index].estDonneur = false
            joueurs[index].pointsRestants = 0
            joueurs[index].cartesNonJouees = []
        }

        if !joueurs.isEmpty {
            joueurs[0].estDonneur = true
        }

        mancheNumero = 0
        indexDonneur = 0
        cases = CarteSpeciale.allCases.map { CasePlateau(carte: $0) }
        cartesJoueesCetteManche = [:]
        transfertsManche = []
        gagnantId = nil
        estGrandOpera = false

        withAnimation(.easeInOut(duration: 0.4)) {
            etat = .attenteManche
        }
    }

    /// Retour a l'ecran d'accueil (nouvelle config)
    func retourAccueil() {
        joueurs = []
        mancheNumero = 0
        cases = CarteSpeciale.allCases.map { CasePlateau(carte: $0) }

        withAnimation(.easeInOut(duration: 0.4)) {
            etat = .accueil
        }
    }

    // MARK: - Utilitaires internes

    /// Avance le donneur au joueur actif suivant
    private func avancerDonneur() {
        // Retirer le donneur precedent
        for index in joueurs.indices {
            joueurs[index].estDonneur = false
        }

        // Avancer l'index
        indexDonneur = (indexDonneur + 1) % joueursActifs.count

        // Trouver le joueur actif correspondant et le marquer donneur
        let actifs = joueursActifs
        if indexDonneur < actifs.count,
           let index = joueurs.firstIndex(where: { $0.id == actifs[indexDonneur].id }) {
            joueurs[index].estDonneur = true
        }
    }
}
