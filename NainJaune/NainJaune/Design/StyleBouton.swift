import SwiftUI

/// Style de bouton principal : fond bordeaux, texte clair
struct BoutonTapisStyle: ButtonStyle {
    var couleurFond: Color = .bordeaux
    var estDesactive: Bool = false

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.system(.title3, design: .rounded, weight: .semibold))
            .foregroundStyle(.cremeCarte)
            .frame(maxWidth: .infinity)
            .frame(minHeight: DesignTokens.hauteurBouton)
            .background(
                RoundedRectangle(cornerRadius: DesignTokens.rayonCarte)
                    .fill(estDesactive ? couleurFond.opacity(0.4) : couleurFond)
                    .shadow(color: .black.opacity(0.3),
                            radius: DesignTokens.ombreCarteRayon,
                            y: DesignTokens.ombreCarteY)
            )
            .scaleEffect(configuration.isPressed ? 0.96 : 1.0)
            .animation(.easeInOut(duration: 0.1), value: configuration.isPressed)
    }
}

/// Style de bouton secondaire : contour bordeaux, fond transparent
struct BoutonSecondaireStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.system(.body, design: .rounded, weight: .medium))
            .foregroundStyle(.bordeaux)
            .frame(maxWidth: .infinity)
            .frame(minHeight: 44)
            .background(
                RoundedRectangle(cornerRadius: DesignTokens.rayonCarte)
                    .stroke(.bordeaux, lineWidth: 2)
            )
            .scaleEffect(configuration.isPressed ? 0.96 : 1.0)
            .animation(.easeInOut(duration: 0.1), value: configuration.isPressed)
    }
}

/// Style de bouton pour les cartes speciales
struct BoutonCarteStyle: ButtonStyle {
    var estJouee: Bool = false

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .frame(maxWidth: .infinity)
            .background(
                RoundedRectangle(cornerRadius: DesignTokens.rayonCarte)
                    .fill(estJouee ? Color.gray.opacity(0.3) : Color.cremeCarte)
                    .shadow(color: .black.opacity(estJouee ? 0.1 : 0.25),
                            radius: DesignTokens.ombreCarteRayon,
                            y: DesignTokens.ombreCarteY)
            )
            .scaleEffect(configuration.isPressed ? 0.95 : 1.0)
            .animation(.easeInOut(duration: 0.1), value: configuration.isPressed)
            .opacity(estJouee ? 0.6 : 1.0)
    }
}
