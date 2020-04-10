// Core Imports
import React from "react"
import { Chip, Tooltip } from "@material-ui/core"

export default function Sparkchips({ ...props }) {
  return (
    <div
      style={{
        display: "flex",
        flexWrap: "wrap",
        justifyContent: "center",
        width: "100%",
      }}
    >
      {(props.items || []).map((item) => (
        <Tooltip title={item.tooltip || ""}>
          <Chip
            key={item.name}
            label={item.name}
            style={{
              margin: 4,
              backgroundColor: item.color,
              color: item.textColor,
            }}
          />
        </Tooltip>
      ))}
    </div>
  )
}
