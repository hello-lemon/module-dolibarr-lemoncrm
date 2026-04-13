import SwiftUI

/// Affiche une case du plateau avec la carte speciale et son pot actuel
struct CasePlateauView: View {
    let casePlateau: CasePlateau
    var estJouee: Bool = false
    var compact: Bool = false

    var body: some View {
        VStack(spacing: compact ? 4 : 8) {
            // Figure et symbole
            HStack(spacing: 2) {
                Text(casePlateau.carte.figure)
                    .font(.system(size: compact ? 18 : 24, weight: .bold, design: .serif))
                Text(casePlateau.carte.symbole)
                    .font(.system(size: compact ? 14 : 18))
            }
            .foregroundStyle(casePlateau.carte.couleurCarte)

            // Nom de la carte
            if !compact {
                Text(casePlateau.carte.nom)
                    .font(.system(size: 11, weight: .medium, design: .rounded))
                    .foregroundStyle(.noirProfond.opacity(0.7))
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
            }

            // Pot de jetons
            HStack(spacing: 4) {
                Image(systemName: "circle.fill")
                    .font(.system(size: compact ? 8 : 10))
                    .foregroundStyle(.orJeton)

                Text("\(casePlateau.pot)")
                    .font(.system(size: compact ? 16 : 22, weight: .bold, design: .monospaced))
                    .foregroundStyle(.orJeton)
                    .animerValeur(casePlateau.pot)
            }

            // Mise de base
            if !compact {
                Text("mise : \(casePlateau.carte.mise)")
                    .font(.system(size: 10, design: .rounded))
                    .foregroundStyle(.noirProfond.opacity(0.5))
            }
        }
        .padding(compact ? 8 : 12)
        .frame(maxWidth: .infinity)
        .background(
            RoundedRectangle(cornerRadius: DesignTokens.rayonCarte)
                .fill(estJouee ? Color.gray.opacity(0.2) : Color.cremeCarte)
                .shadow(color: .black.opacity(estJouee ? 0.1 : 0.25),
                        radius: DesignTokens.ombreCarteRayon,
                        y: DesignTokens.ombreCarteY)
        )
        .opacity(estJouee ? 0.5 : 1.0)
    }
}
