import SwiftUI

/// Permet de selectionner le gagnant de la manche parmi les joueurs actifs
struct SelectionGagnantView: View {
    @Environment(GestionnaireDeJeu.self) private var jeu

    var body: some View {
        VStack(alignment: .leading, spacing: DesignTokens.espacement) {
            Text("Qui a gagne la manche ?")
                .font(.system(.headline, design: .rounded, weight: .semibold))
                .foregroundStyle(.cremeCarte)

            // Grille de joueurs
            LazyVGrid(columns: [
                GridItem(.flexible()),
                GridItem(.flexible())
            ], spacing: 10) {
                ForEach(jeu.joueursActifs) { joueur in
                    let estSelectionne = jeu.gagnantId == joueur.id

                    Button {
                        jeu.selectionnerGagnant(joueurId: joueur.id)
                    } label: {
                        VStack(spacing: 6) {
                            if estSelectionne {
                                Image(systemName: "crown.fill")
                                    .font(.title3)
                                    .foregroundStyle(.orJeton)
                            }

                            Text(joueur.prenom)
                                .font(.system(.body, design: .rounded, weight: estSelectionne ? .bold : .medium))
                                .foregroundStyle(estSelectionne ? .orJeton : .cremeCarte)
                                .lineLimit(1)
                                .minimumScaleFactor(0.8)
                        }
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 14)
                        .background(
                            RoundedRectangle(cornerRadius: 12)
                                .fill(estSelectionne ? Color.bordeaux : Color.vertTapis.opacity(0.4))
                                .overlay(
                                    RoundedRectangle(cornerRadius: 12)
                                        .stroke(estSelectionne ? Color.orJeton : Color.clear, lineWidth: 2)
                                )
                        )
                    }
                }
            }
        }
    }
}
