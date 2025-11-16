; =============================================
; PIGGY POO - Now with Music and SFX!
; =============================================

  .inesprg 1
  .ineschr 1
  .inesmap 0
  .inesmir 1

;; NEW! --- Include the Sound Engine ---
  .include "famitone2.asm"

; --- PPU Defines ---
PPU_CTRL = $2000
PPU_MASK = $2001

; --- RAM Variables ---
VIBE_REGISTER = $00
PLAYER_MAP_Y = $01
PLAYER_MAP_X = $02

; --- VIBE CODE DEFINES ---
VIBE_IDLE = 0
VIBE_PLAYER_ACTION = 1
VIBE_LEVEL_COMPLETE = 2

;; NEW! --- SFX Defines ---
SFX_PUSH = 0
SFX_WIN  = 1

; --- Map/Tile Defines ---
TILE_GOAL = $3F

; --- FamiTone Aliases ---
MusicPlay = FamiToneMusicPlay
MusicStop = FamiToneMusicStop
FxPlay = FamiToneSfxPlay

  .bank 0
  .org $C000

RESET:
  SEI
  CLD
  LDX #$FF
  TXS

  ;; NEW! --- Initialize FamiTone2 ---
  JSR FamiToneInit

  ; ... (rest of the initial PPU setup is the same)

  JSR LoadPalette
  JSR LoadLevel
  JSR DrawBackground

  ;; NEW! --- Load and start the music ---
  LDA #0
  JSR MusicPlay

  LDA #%10010000   ; Enable NMI
  STA PPU_CTRL
  LDA #%00011110   ; Enable rendering
  STA PPU_MASK

GameLoop:
  ;; NEW! --- Update the sound engine every frame ---
  JSR FamiToneUpdate

  JSR ReadController
  JSR UpdateGame
  JMP GameLoop

NMI:
  ; Push registers to stack
  PHA
  TXA
  PHA
  TYA
  PHA

  ;; NEW! --- The music driver must NOT be called from the NMI ---
  ;; It is now called in the main GameLoop.

  JSR DrawSprites

  ; Reset Vibe Register for the next frame
  LDA #VIBE_IDLE
  STA VIBE_REGISTER

  ; Pull registers from stack
  PLA
  TAY
  PLA
  TAX
  PLA
  RTI

; ===========================================
; Main Subroutines
; ===========================================
LoadPalette:
  RTS
LoadLevel:
  RTS
DrawBackground:
  RTS
ReadController:
  RTS
UpdateGame:
  RTS
DrawSprites:
  RTS
GetTileAtCoords:
  RTS

TryPushBale:
  ; (Previous logic to check if a push is valid)
  ; ...
  ; If push is valid:

  ;; NEW! --- Play the "push" sound effect ---
  LDA #SFX_PUSH
  JSR FxPlay

  ; --- VIBE CODE TRIGGER ---
  LDA #VIBE_PLAYER_ACTION
  STA VIBE_REGISTER

  ; (Rest of the bale push logic)
  ; ...
  JMP CheckWinCondition

CheckWinCondition:
  LDA PLAYER_MAP_Y
  LDY PLAYER_MAP_X
  JSR GetTileAtCoords
  CMP #TILE_GOAL
  BNE NotWin

  ; YOU WIN!

  ;; NEW! --- Stop the music and play the "win" sound effect ---
  JSR MusicStop
  LDA #SFX_WIN
  JSR FxPlay

  ; --- VIBE CODE TRIGGER ---
  LDA #VIBE_LEVEL_COMPLETE
  STA VIBE_REGISTER

  ; Freeze game
WinLoop:
  JMP WinLoop
NotWin:
  RTS

; ===========================================
; Data Section
; ===========================================
PALETTE_DATA:
  ; (same as before)

LEVEL_1_DATA:
  ; (same as before)

  .bank 1
  .org $E000  ;; NEW! --- Move vectors to leave space for music data

;; NEW! --- Include the music and sound data ---
  .include "music.asm"

IRQ_HANDLER:
  RTI

  .org $FFFA
  .dw NMI
  .dw RESET
  .dw IRQ_HANDLER ; From FamiTone2

  .bank 2
  .org $0000
  .incbin "graphics.chr"