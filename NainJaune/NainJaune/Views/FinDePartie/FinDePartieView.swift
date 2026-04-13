import SwiftUI

/// Ecran de fin de partie : classement final et options
struct FinDePartieView: View {
    @Environment(GestionnaireDeJeu.self) private var jeu
    @State private var apparition = false

    var body: some View {
        VStack(spacing: DesignTokens.espacementLarge) {
            Spacer()

            // Titre
            VStack(spacing: 8) {
                Text("Partie terminee !")
                    .font(.system(.largeTitle, design: .serif, weight: .bold))
                    .foregroundStyle(.orJeton)

                Text("Apres \(jeu.mancheNumero) manches")
                    .font(.system(.body, design: .rounded))
                    .foregroundStyle(.cremeCarte.opacity(0.7))

                // Raison de la fin
                if let elimine = jeu.joueursActifs.first(where: { !$0.peutMiser }) ?? jeu.joueurs.first(where: { $0.estElimine }) {
                    Text("\(elimine.prenom) ne peut plus miser")
                        .font(.system(.caption, design: .rounded))
                        .foregroundStyle(.rougeAlerte)
                }
            }
            .opacity(apparition ? 1 : 0)
            .scaleEffect(apparition ? 1 : 0.8)

            // Classement
            VStack(spacing: 2) {
                ForEach(Array(jeu.classement().enumerated()), id: \.element.id) { index, joueur in
                    ligneClassement(position: index + 1, joueur: joueur)
                        .opacity(apparition ? 1 : 0)
                        .offset(y: apparition ? 0 : CGFloat(20 * (index + 1)))
                }
            }
            .padding(.horizontal)

            Spacer()

            // Boutons d'action
            VStack(spacing: DesignTokens.espacement) {
                Button {
                    HaptiqueManager.impactMoyen()
                    jeu.recommencer()
                } label: {
                    HStack(spacing: 8) {
                        Image(systemName: "arrow.counterclockwise")
                        Text("Rejouer (memes joueurs)")
                    }
                }
                .buttonStyle(BoutonTapisStyle())

                Button {
                    HaptiqueManager.selection()
                    jeu.retourAccueil()
                } label: {
                    Text("Nouvelle configuration")
                }
                .buttonStyle(BoutonSecondaireStyle())
            }
            .padding(.horizontal, 40)
            .padding(.bottom, 40)
        }
        .onAppear {
            withAnimation(.easeOut(duration: 0.8)) {
                apparition = true
            }
        }
    }

    // MARK: - Ligne du classement

    @ViewBuilder
    private func ligneClassement(position: Int, joueur: Joueur) -> some View {
        let estPremier = position == 1

        HStack(spacing: DesignTokens.espacement) {
            // Position
            ZStack {
                if estPremier {
                    Image(systemName: "crown.fill")
                        .font(.title3)
                        .foregroundStyle(.orJeton)
                } else {
                    Text("\(position)")
                        .font(.system(.title3, design: .monospaced, weight: .bold))
                        .foregroundStyle(.cremeCarte.opacity(0.6))
                }
            }
            .frame(width: 40)

            // Prenom
            Text(joueur.prenom)
                .font(.system(estPremier ? .title3 : .body,
                              design: .rounded,
                              weight: estPremier ? .bold : .medium))
                .foregroundStyle(estPremier ? .orJeton : .cremeCarte)

            Spacer()

            // Solde final
            Text("\(joueur.solde)")
                .font(.system(estPremier ? .title2 : .title3,
                              design: .monospaced,
                              weight: .bold))
                .foregroundStyle(joueur.solde >= 0 ? (estPremier ? .orJeton : .cremeCarte) : .rougeAlerte)

            Image(systemName: "circle.fill")
                .font(.system(size: 8))
                .foregroundStyle(.orJeton.opacity(estPremier ? 0.8 : 0.4))
        }
        .padding(.horizontal, DesignTokens.espacement)
        .padding(.vertical, estPremier ? 16 : 10)
        .background(
            RoundedRectangle(cornerRadius: 12)
                .fill(estPremier ? Color.bordeaux.opacity(0.3) : Color.vertTapis.opacity(0.2))
                .overlay(
                    RoundedRectangle(cornerRadius: 12)
                        .stroke(estPremier ? Color.orJeton.opacity(0.4) : Color.clear, lineWidth: 1)
                )
        )
    }
}
