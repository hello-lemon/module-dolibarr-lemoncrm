import SwiftUI

/// Sheet pour selectionner quel joueur a joue une carte speciale
struct SelectionJoueurSheet: View {
    @Environment(GestionnaireDeJeu.self) private var jeu
    let carte: CarteSpeciale
    let onSelection: (UUID) -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            VStack(spacing: DesignTokens.espacement) {
                // Carte concernee
                VStack(spacing: 4) {
                    Text("Qui a pose le")
                        .font(.system(.body, design: .rounded))
                        .foregroundStyle(.cremeCarte.opacity(0.8))

                    HStack(spacing: 4) {
                        Text(carte.figure)
                            .font(.system(.title, design: .serif, weight: .bold))
                        Text(carte.symbole)
                            .font(.system(.title2))
                    }
                    .foregroundStyle(carte.couleurCarte)

                    Text(carte.nom)
                        .font(.system(.headline, design: .rounded))
                        .foregroundStyle(.cremeCarte)
                }
                .padding()

                // Liste des joueurs actifs
                ForEach(jeu.joueursActifs) { joueur in
                    Button {
                        onSelection(joueur.id)
                        HaptiqueManager.succes()
                        dismiss()
                    } label: {
                        HStack {
                            Text(joueur.prenom)
                                .font(.system(.title3, design: .rounded, weight: .medium))
                                .foregroundStyle(.cremeCarte)

                            Spacer()

                            Text("\(joueur.solde) jetons")
                                .font(.system(.body, design: .monospaced))
                                .foregroundStyle(.orJeton)
                        }
                        .padding()
                        .background(
                            RoundedRectangle(cornerRadius: 12)
                                .fill(Color.vertTapis.opacity(0.5))
                        )
                    }
                    .padding(.horizontal)
                }

                Spacer()
            }
            .background(Color.fondApp)
            .navigationTitle("Choisir le joueur")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Annuler") {
                        dismiss()
                    }
                    .foregroundStyle(.orJeton)
                }
            }
            .toolbarBackground(Color.fondApp, for: .navigationBar)
        }
    }
}
