import SwiftUI

/// Affiche un joueur dans la liste du plateau : prenom, solde, indicateur donneur
struct LigneJoueurPlateauView: View {
    let joueur: Joueur
    var estGagnant: Bool = false

    var body: some View {
        HStack(spacing: DesignTokens.espacement) {
            // Indicateur donneur
            if joueur.estDonneur {
                Image(systemName: "suit.diamond.fill")
                    .font(.caption)
                    .foregroundStyle(.orJeton)
            }

            // Prenom
            Text(joueur.prenom)
                .font(.system(.body, design: .rounded, weight: joueur.estDonneur ? .bold : .medium))
                .foregroundStyle(.cremeCarte)
                .lineLimit(1)

            Spacer()

            // Indicateur gagnant
            if estGagnant {
                Image(systemName: "crown.fill")
                    .font(.caption)
                    .foregroundStyle(.orJeton)
            }

            // Solde
            HStack(spacing: 4) {
                Text("\(joueur.solde)")
                    .font(.system(.title3, design: .monospaced, weight: .bold))
                    .foregroundStyle(joueur.solde >= 0 ? .orJeton : .rougeAlerte)
                    .animerValeur(joueur.solde)

                Image(systemName: "circle.fill")
                    .font(.system(size: 8))
                    .foregroundStyle(.orJeton.opacity(0.6))
            }
        }
        .padding(.horizontal, DesignTokens.espacement)
        .padding(.vertical, 10)
        .background(
            RoundedRectangle(cornerRadius: 10)
                .fill(joueur.estDonneur ? Color.vertTapis.opacity(0.6) : Color.clear)
        )
    }
}
