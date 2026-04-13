import SwiftUI

/// Ligne de saisie du prenom d'un joueur dans la configuration
struct LigneJoueurView: View {
    let index: Int
    @Binding var joueur: Joueur
    let peutSupprimer: Bool
    let onSupprimer: () -> Void

    var body: some View {
        HStack(spacing: DesignTokens.espacement) {
            // Numero du joueur
            Text("\(index + 1)")
                .font(.system(.headline, design: .monospaced, weight: .bold))
                .foregroundStyle(.orJeton)
                .frame(width: 30)

            // Champ de saisie du prenom
            TextField("Prenom du joueur \(index + 1)", text: $joueur.prenom)
                .font(.system(.body, design: .rounded))
                .textFieldStyle(.plain)
                .padding(.horizontal, 12)
                .padding(.vertical, 10)
                .background(
                    RoundedRectangle(cornerRadius: 10)
                        .fill(Color.cremeCarte)
                )
                .foregroundStyle(.noirProfond)
                .autocorrectionDisabled()
                .textInputAutocapitalization(.words)

            // Bouton supprimer
            if peutSupprimer {
                Button(action: onSupprimer) {
                    Image(systemName: "xmark.circle.fill")
                        .font(.title3)
                        .foregroundStyle(.rougeAlerte.opacity(0.7))
                }
            }
        }
    }
}
