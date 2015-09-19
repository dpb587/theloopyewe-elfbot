-- argv: template-file prints
on run argv
    tell application "DYMO Label"
        openLabel in (item 1 of argv)
        
        repeat (item 2 of argv) times
            printLabel2
        end repeat
    end tell
end run
