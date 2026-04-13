import SwiftUI

/// Liste des joueurs avec leurs soldes
struct ListeJoueursView: View {
    @Environment(GestionnaireDeJeu.self) private var jeu

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            // Titre section
            HStack {
                Text("Joueurs")
                    .font(.system(.headline, design: .rounded, weight: .semibold))
                    .foregroundStyle(.cremeCarte)

                Spacer()

                Text("Manche \(jeu.mancheNumero)")
                    .font(.system(.caption, design: .rounded))
                    .foregroundStyle(.cremeCarte.opacity(0.6))
            }
            .padding(.horizontal, DesignTokens.espacement)
            .padding(.top, 8)

            // Liste des joueurs
            ForEach(jeu.joueursActifs) { joueur in
                LigneJoueurPlateauView(joueur: joueur)
            }

            // Joueurs elimines (si il y en a)
            let elimines = jeu.joueurs.filter { $0.estElimine }
            if !elimines.isEmpty {
                Divider()
                    .background(Color.cremeCarte.opacity(0.3))
                    .padding(.horizontal)

                ForEach(elimines) { joueur in
                    LigneJoueurPlateauView(joueur: joueur)
                        .opacity(0.4)
                        .strikethrough()
                }
            }
        }
        .padding(.vertical, 8)
        .background(
            RoundedRectangle(cornerRadius: DesignTokens.rayonCarte)
                .fill(Color.vertTapis.opacity(0.4))
        )
    }
}
