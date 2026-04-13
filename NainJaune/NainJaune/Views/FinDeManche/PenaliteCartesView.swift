import SwiftUI

/// Declaration des cartes speciales encore en main (penalites)
struct PenaliteCartesView: View {
    @Environment(GestionnaireDeJeu.self) private var jeu

    var body: some View {
        VStack(alignment: .leading, spacing: DesignTokens.espacement) {
            Text("Cartes speciales non jouees")
                .font(.system(.headline, design: .rounded, weight: .semibold))
                .foregroundStyle(.cremeCarte)

            if jeu.cartesNonJoueesCetteManche.isEmpty {
                // Toutes les cartes ont ete jouees
                HStack(spacing: 8) {
                    Image(systemName: "checkmark.circle.fill")
                        .foregroundStyle(.vertReussite)
                    Text("Toutes les cartes speciales ont ete jouees !")
                        .font(.system(.body, design: .rounded))
                        .foregroundStyle(.cremeCarte.opacity(0.8))
                }
                .padding()
            } else {
                Text("Qui a encore ces cartes en main ?")
                    .font(.system(.caption, design: .rounded))
                    .foregroundStyle(.cremeCarte.opacity(0.6))

                ForEach(jeu.cartesNonJoueesCetteManche, id: \.id) { carte in
                    carteNonJoueeSection(carte: carte)
                }
            }
        }
    }

    @ViewBuilder
    private func carteNonJoueeSection(carte: CarteSpeciale) -> some View {
        let caseCorrespondante = jeu.cases.first { $0.carte == carte }
        let potActuel = caseCorrespondante?.pot ?? 0

        VStack(alignment: .leading, spacing: 8) {
            HStack {
                // Nom de la carte
                HStack(spacing: 4) {
                    Text(carte.figure)
                        .font(.system(.body, design: .serif, weight: .bold))
                    Text(carte.symbole)
                        .font(.system(.caption))
                }
                .foregroundStyle(carte.couleurCarte)

                Text(carte.nom)
                    .font(.system(.body, design: .rounded))
                    .foregroundStyle(.cremeCarte)

                Spacer()

                // Penalite potentielle
                Text("Penalite : \(potActuel)")
                    .font(.system(.caption, design: .monospaced, weight: .bold))
                    .foregroundStyle(.rougeAlerte)
            }

            // Boutons joueurs (sauf le gagnant)
            let perdants = jeu.joueursActifs.filter { $0.id != jeu.gagnantId }
            LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 6) {
                ForEach(perdants) { joueur in
                    let aLaCarte = joueur.cartesNonJouees.contains(carte)

                    Button {
                        if aLaCarte {
                            jeu.annulerCarteNonJouee(carte: carte, joueurId: joueur.id)
                        } else {
                            jeu.declarerCarteNonJouee(carte: carte, joueurId: joueur.id)
                        }
                        HaptiqueManager.selection()
                    } label: {
                        HStack(spacing: 6) {
                            Image(systemName: aLaCarte ? "checkmark.circle.fill" : "circle")
                                .foregroundStyle(aLaCarte ? .rougeAlerte : .cremeCarte.opacity(0.5))
                                .font(.body)

                            Text(joueur.prenom)
                                .font(.system(.caption, design: .rounded, weight: .medium))
                                .foregroundStyle(.cremeCarte)
                                .lineLimit(1)
                        }
                        .padding(.vertical, 8)
                        .padding(.horizontal, 10)
                        .frame(maxWidth: .infinity)
                        .background(
                            RoundedRectangle(cornerRadius: 8)
                                .fill(aLaCarte ? Color.rougeAlerte.opacity(0.2) : Color.vertTapis.opacity(0.3))
                        )
                    }
                }
            }
        }
        .padding(DesignTokens.espacement)
        .background(
            RoundedRectangle(cornerRadius: 12)
                .fill(Color.vertTapis.opacity(0.3))
        )
    }
}
