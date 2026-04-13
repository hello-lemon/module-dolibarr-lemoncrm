import SwiftUI

/// Section de saisie des points pour chaque perdant
struct SaisiePointsView: View {
    @Environment(GestionnaireDeJeu.self) private var jeu

    var body: some View {
        VStack(alignment: .leading, spacing: DesignTokens.espacement) {
            Text("Points restants en main")
                .font(.system(.headline, design: .rounded, weight: .semibold))
                .foregroundStyle(.cremeCarte)

            Text("Chaque perdant declare ses cartes restantes")
                .font(.system(.caption, design: .rounded))
                .foregroundStyle(.cremeCarte.opacity(0.6))

            // Une mini-calculatrice par perdant
            ForEach(perdants) { joueur in
                MiniCalculatriceView(joueur: joueur) { points in
                    jeu.saisirPointsRestants(joueurId: joueur.id, points: points)
                }
            }
        }
    }

    private var perdants: [Joueur] {
        guard let gagnantId = jeu.gagnantId else { return [] }
        return jeu.joueursActifs.filter { $0.id != gagnantId }
    }
}
