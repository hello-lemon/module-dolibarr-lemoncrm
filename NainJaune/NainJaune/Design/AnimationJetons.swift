import SwiftUI

/// Modificateur pour animer un changement de valeur numerique (solde, pot)
struct AnimationValeurModifier: ViewModifier {
    let valeur: Int

    func body(content: Content) -> some View {
        content
            .contentTransition(.numericText(value: Double(valeur)))
            .animation(.snappy(duration: 0.3), value: valeur)
    }
}

extension View {
    /// Anime les changements de valeur numerique avec une transition fluide
    func animerValeur(_ valeur: Int) -> some View {
        modifier(AnimationValeurModifier(valeur: valeur))
    }
}

/// Vue de jeton animee avec un petit rebond
struct JetonAnimeView: View {
    let montant: Int
    var taille: CGFloat = 28
    var couleurFond: Color = .orJeton

    @State private var echelle: CGFloat = 1.0

    var body: some View {
        Text("\(montant)")
            .font(.system(size: taille * 0.5, weight: .bold, design: .monospaced))
            .foregroundStyle(.noirProfond)
            .frame(width: taille, height: taille)
            .background(
                Circle()
                    .fill(couleurFond)
                    .shadow(color: couleurFond.opacity(0.4), radius: 2, y: 1)
            )
            .scaleEffect(echelle)
            .onChange(of: montant) {
                withAnimation(.spring(response: 0.3, dampingFraction: 0.5)) {
                    echelle = 1.2
                }
                withAnimation(.spring(response: 0.3, dampingFraction: 0.5).delay(0.15)) {
                    echelle = 1.0
                }
            }
    }
}
