import SwiftUI

/// Recapitulatif des transferts avant validation de fin de manche
struct RecapitulatifTransfertView: View {
    @Environment(GestionnaireDeJeu.self) private var jeu

    var body: some View {
        VStack(alignment: .leading, spacing: DesignTokens.espacement) {
            Text("Recapitulatif des transferts")
                .font(.system(.headline, design: .rounded, weight: .semibold))
                .foregroundStyle(.cremeCarte)

            if let gagnant = jeu.gagnant {
                // Resume pour le gagnant
                let gains = calculerGains(pourGagnant: gagnant)
                VStack(alignment: .leading, spacing: 6) {
                    HStack {
                        Image(systemName: "crown.fill")
                            .foregroundStyle(.orJeton)
                        Text(gagnant.prenom)
                            .font(.system(.body, design: .rounded, weight: .bold))
                            .foregroundStyle(.orJeton)
                        Spacer()
                        Text("+\(gains)")
                            .font(.system(.title3, design: .monospaced, weight: .bold))
                            .foregroundStyle(.vertReussite)
                    }
                }
                .padding(10)
                .background(
                    RoundedRectangle(cornerRadius: 10)
                        .fill(Color.vertReussite.opacity(0.15))
                )

                // Resume pour chaque perdant
                let perdants = jeu.joueursActifs.filter { $0.id != jeu.gagnantId }
                ForEach(perdants) { joueur in
                    let perte = calculerPertes(pourJoueur: joueur)
                    HStack {
                        Text(joueur.prenom)
                            .font(.system(.body, design: .rounded, weight: .medium))
                            .foregroundStyle(.cremeCarte)
                        Spacer()
                        Text("-\(perte)")
                            .font(.system(.body, design: .monospaced, weight: .bold))
                            .foregroundStyle(.rougeAlerte)
                    }
                    .padding(.horizontal, 10)
                    .padding(.vertical, 6)
                }

                // Grand Opera
                if jeu.estGrandOpera {
                    HStack(spacing: 6) {
                        Image(systemName: "sparkles")
                            .foregroundStyle(.orJeton)
                        Text("Grand Opera : toutes les cases sont raflees !")
                            .font(.system(.caption, design: .rounded, weight: .medium))
                            .foregroundStyle(.orJeton)
                    }
                    .padding(8)
                    .background(
                        RoundedRectangle(cornerRadius: 8)
                            .fill(Color.orJeton.opacity(0.15))
                    )
                }

                // Penalites
                let penalites = calculerPenalites()
                if !penalites.isEmpty {
                    VStack(alignment: .leading, spacing: 4) {
                        Text("Penalites")
                            .font(.system(.caption, design: .rounded, weight: .semibold))
                            .foregroundStyle(.rougeAlerte)

                        ForEach(penalites, id: \.0) { nom, carte, montant in
                            HStack {
                                Text("\(nom) → \(carte)")
                                    .font(.system(.caption, design: .rounded))
                                    .foregroundStyle(.cremeCarte.opacity(0.8))
                                Spacer()
                                Text("-\(montant)")
                                    .font(.system(.caption, design: .monospaced, weight: .bold))
                                    .foregroundStyle(.rougeAlerte)
                            }
                        }
                    }
                    .padding(8)
                    .background(
                        RoundedRectangle(cornerRadius: 8)
                            .fill(Color.rougeAlerte.opacity(0.1))
                    )
                }
            }
        }
    }

    // MARK: - Calculs de preview

    private func calculerGains(pourGagnant gagnant: Joueur) -> Int {
        var total = 0

        // Points des perdants
        let perdants = jeu.joueursActifs.filter { $0.id != jeu.gagnantId }
        for joueur in perdants {
            total += joueur.pointsRestants
        }

        // Grand Opera : toutes les cases
        if jeu.estGrandOpera {
            total += jeu.totalPots
        }

        return total
    }

    private func calculerPertes(pourJoueur joueur: Joueur) -> Int {
        var total = joueur.pointsRestants

        // Penalites cartes non jouees
        for carte in joueur.cartesNonJouees {
            if let casePlateau = jeu.cases.first(where: { $0.carte == carte }) {
                total += casePlateau.pot
            }
        }

        return total
    }

    private func calculerPenalites() -> [(String, String, Int)] {
        var resultat: [(String, String, Int)] = []
        let perdants = jeu.joueursActifs.filter { $0.id != jeu.gagnantId }

        for joueur in perdants {
            for carte in joueur.cartesNonJouees {
                if let casePlateau = jeu.cases.first(where: { $0.carte == carte }) {
                    let montant = casePlateau.pot
                    if montant > 0 {
                        resultat.append((joueur.prenom, carte.nom, montant))
                    }
                }
            }
        }

        return resultat
    }
}
