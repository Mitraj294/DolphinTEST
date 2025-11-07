import sys
import re
import matplotlib.pyplot as plt
import matplotlib.gridspec as gridspec
from matplotlib.ticker import FixedLocator
from matplotlib.patches import FancyBboxPatch
import os
import glob

# Custom Color for Background:
r = 36 / 255
g = 53 / 255
b = 144 / 255
dolphin_blue = (r,g,b)

def extract_all_data(file_path):
    with open(file_path, 'r') as file:
        content = file.read()

    meta_pattern = re.compile(
        r"Test Subject:\s*(.+?)\s+"
        r"Self Total Words:\s*(\d+)\s+"
        r"Concept Total Words:\s*(\d+)\s+"
        r"Adjusted Total Words:\s*(\d+)\s"
    )

    meta_match = meta_pattern.search(content)

    if not meta_match:
        return None, None, None, None, None

    name = meta_match.group(1)
    self_total = int(meta_match.group(2))
    con_total = int(meta_match.group(3))
    adj_total = int(meta_match.group(4))

    # Capture both projections and decision approach
    pattern = re.compile(
        r"\*{7} Self Projection \*{7}\s+"
        r"A percent: ([\d\.]+)%\s+"
        r"B percent: ([\d\.]+)%\s+"
        r"C percent: ([\d\.]+)%\s+"
        r"D percent: ([\d\.]+)%\s+"
        r"Average: ([\d\.]+)%\s+"
        r"Decision Approach: ([\d\.]+)%.*?"
        r"\*{7} Concept Projection \*{7}\s+"
        r"A percent: ([\d\.]+)%\s+"
        r"B percent: ([\d\.]+)%\s+"
        r"C percent: ([\d\.]+)%\s+"
        r"D percent: ([\d\.]+)%\s+"
        r"Average: ([\d\.]+)%", 
        re.DOTALL
    )

    match = pattern.search(content)
    if match:
        self_proj = [float(match.group(i)) for i in range(1, 6)]
        decision_value = float(match.group(6))
        concept_proj = [float(match.group(i)) for i in range(7, 12)]
        return name, self_total, con_total, adj_total, self_proj, decision_value, concept_proj
    return name, self_total, None, None, None, None, None

def plot_projection(ax, percentages, title, dot_labels=None, decision_value=None):
    base_labels = ['A', 'B', 'C', 'D']
    values = percentages[:4]
    average = percentages[4]

    labels = base_labels.copy()

    y_positions = list(range(len(values)))[::-1]
    if decision_value is not None:
        labels.append('Decision')
        values_with_decision = values + [decision_value]
    else:
        values_with_decision = values

    y_positions = list(range(len(labels)))[::-1]


    # Background bars
    ax.barh(labels, [100] * len(labels), color='white', height=1, alpha=0.1, edgecolor='gray', linewidth = 1.5)

    # Connect A–D values with blue line (skip Decision)
    if decision_value is not None:
        ax.plot(values, y_positions[:4], color = dolphin_blue, linestyle = 'solid', linewidth=3)
    else:
        ax.plot(values, y_positions, color = dolphin_blue, linestyle = 'solid', linewidth=3)

    for i, (val, y) in enumerate(zip(values_with_decision, y_positions)):
        if decision_value is not None and i == len(values_with_decision) - 1:
            ax.plot(val, y, color = 'dodgerblue', marker = 'o', markeredgecolor = 'black', markeredgewidth = 1.5, markersize=12)  # Red for Decision
        else:
            ax.plot(val, y, color = dolphin_blue, marker = 'o', markeredgecolor = 'white', markeredgewidth = 1.5, markersize=20)
            if dot_labels and i < len(dot_labels):
                ax.text(val, y, dot_labels[i], color='white', ha='center', va='center',
                        fontsize=9, fontweight='bold')
    
    x_positions = [float(i * (25 / 11)) for i in range(45)]

    # Set a fixed locator so matplotlib doesn't complain
    ax.xaxis.set_major_locator(FixedLocator(x_positions))

    # Average vertical dashed line
    ax.axvline(x=average, color='grey', linestyle='--', linewidth=1.5)
    ax.set_xlim(0, 100)
    ax.set_yticks(y_positions)
    ax.set_yticklabels([''] * len(y_positions))
    ax.tick_params(axis='y', length = 0)
    # Remove the labels under the ticks
    ax.set_xticklabels([''] * len(x_positions))
    ax.tick_params(axis='x', which='both', bottom=True, top=True, 
                   labelbottom=False, labeltop=False, length = 10)
    ax.set_title(title)


# Low Prefer: 1 - 5 notches to the left of the green center line
#   Low Want: 6 - 10 notches to the left
#   Low Need: 11 or more notches to the left 
#   Note: Zero of any Element always defaults to the "Need"
#   High Prefer: On the green center point line to 5 notches to the right
#   High Want: 6 - 10 notches to the right
#   High Need: 11 or more notches to the right
def char_decider(value, average):
    notch = float(25/11)
    diff = abs(float(value-average))
    diff = diff/notch
    if value == 0:
        return 'n'
    elif diff <= 5:
        return 'p'
    elif diff <= 10:
        return 'w'
    else:
        return 'n'

def main():
    # Find all files in test_output/ that end with _test.txt
    if len(sys.argv) < 2:
        print("Usage: python3 chart_generator.py <filename>")
        sys.exit(1)

    repeat = False

    file_path = "test_output/" + sys.argv[1]
    if not file_path:
        raise FileNotFoundError("No _test.txt file found in test_output/")
    
    if file_path.endswith("repeat_test.txt"):
        repeat = True

    if os.path.exists(file_path):
        name, self_total, con_total, adj_total, self_proj, decision_value, concept_proj = extract_all_data(file_path)

        if self_proj and concept_proj:
            fig = plt.figure(figsize=(15, 7))
            gs = gridspec.GridSpec(2, 2, width_ratios=[1, 9], wspace=0.05, hspace=0.3)

            # Left metadata panel
            meta_ax = fig.add_subplot(gs[:, 0])
            meta_ax.axis("off")
            
            self_height = 0.945
            self_spacer = 0.071


            # Self Wiring right side of graph text blocks:
            meta_ax.text(10.29, self_height, "Independent",
                         bbox=dict(facecolor="white", edgecolor='white'),
                         color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            meta_ax.text(10.29, (self_height - self_spacer), "External Processor",
                         bbox=dict(facecolor="white", edgecolor='white'),
                         color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            meta_ax.text(10.29, (self_height - (self_spacer*2)), "Methodical",
                         bbox=dict(facecolor="white", edgecolor='white'),
                         color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            meta_ax.text(10.29, (self_height - (self_spacer*3)), "Structured",
                         bbox=dict(facecolor="white", edgecolor='white'),
                         color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            meta_ax.text(10.29, (self_height - (self_spacer*4)), "Decision Approach",
                         bbox=dict(facecolor="white", edgecolor='white'),
                         color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            
            # Left side
            meta_ax.text(1.192, self_height, "Collaborative",
                         bbox=dict(facecolor="white", edgecolor='white'),
                         color=dolphin_blue, fontsize=18.5, ha='right', va='center', family='arial')
            meta_ax.text(1.192, (self_height - self_spacer), "Internal Processor",
                         bbox=dict(facecolor="white", edgecolor='white'),
                         color=dolphin_blue, fontsize=18.5, ha='right', va='center', family='arial')
            meta_ax.text(1.192, (self_height - (self_spacer*2)), "Urgency",
                         bbox=dict(facecolor="white", edgecolor='white'),
                         color=dolphin_blue, fontsize=18.5, ha='right', va='center', family='arial')
            meta_ax.text(1.192, (self_height - (self_spacer*3)), "Unstructured",
                         bbox=dict(facecolor="white", edgecolor='white'),
                         color=dolphin_blue, fontsize=18.5, ha='right', va='center', family='arial')
            meta_ax.text(1.192, (self_height - (self_spacer*4)), "Decision Approach",
                         bbox=dict(facecolor="white", edgecolor='white'),
                         color=dolphin_blue, fontsize=18.5, ha='right', va='center', family='arial')
            
            # Adaptive Self right side of graph text blocks
            adapt_height = 0.315
            adapt_spacer = 0.075

            meta_ax.text(10.29, adapt_height, "Independent",
                         bbox=dict(facecolor="white", edgecolor='white', pad=7),
                         color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            meta_ax.text(10.29, (adapt_height - adapt_spacer), "External Processor",
                         bbox=dict(facecolor="white", edgecolor='white', pad=7),
                         color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            meta_ax.text(10.29, (adapt_height - (adapt_spacer * 2)), "Methodical",
                         bbox=dict(facecolor="white", edgecolor='white', pad=7),
                         color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            meta_ax.text(10.29, (adapt_height - (adapt_spacer * 3)), "Structured",
                         bbox=dict(facecolor="white", edgecolor='white', pad=7),
                         color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            
            # Left Side
            meta_ax.text(1.2, adapt_height, "Collaborative",
                         bbox=dict(facecolor="white", edgecolor='white', pad=7),
                         color=dolphin_blue, fontsize=18.5, ha='right', va='center', family='arial')
            meta_ax.text(1.2, (adapt_height - adapt_spacer), "Internal Processor",
                         bbox=dict(facecolor="white", edgecolor='white', pad=7),
                         color=dolphin_blue, fontsize=18.5, ha='right', va='center', family='arial')
            meta_ax.text(1.2, (adapt_height - (adapt_spacer * 2)), "Urgency",
                         bbox=dict(facecolor="white", edgecolor='white', pad=7),
                         color=dolphin_blue, fontsize=18.5, ha='right', va='center', family='arial')
            meta_ax.text(1.2, (adapt_height - (adapt_spacer * 3)), "Unstructured",
                         bbox=dict(facecolor="white", edgecolor='white', pad=7),
                         color=dolphin_blue, fontsize=18.5, ha='right', va='center', family='arial')


            # Response Outcomes:
            meta_ax.text(1.1929, 1.015, f"RO:{self_total}",
                         bbox=dict(facecolor="white", edgecolor='grey'),
                         color=dolphin_blue, fontsize=18.5, ha='right', va='center', family='arial')
            meta_ax.text(1.19, 0.39, f"RO:{con_total}",
                         bbox=dict(facecolor="white", edgecolor='grey'),
                         color=dolphin_blue, fontsize=18.5, ha='right', va='center', family='arial')
            
            # Turn tick marks into boxes, poor solution:
            meta_ax.text(1.31, 0.994, f"                                                                                                                            ",
                bbox=dict(facecolor="white", edgecolor='black'),
                color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            meta_ax.text(1.31, 0.62, f"                                                                                                                            ",
                bbox=dict(facecolor="white", edgecolor='black'),
                color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            meta_ax.text(1.31, 0.368, f"                                                                                                                            ",
                bbox=dict(facecolor="white", edgecolor='black'),
                color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            meta_ax.text(1.31, 0.051, f"                                                                                                                            ",
                bbox=dict(facecolor="white", edgecolor='black'),
                color=dolphin_blue, fontsize=18.5, ha='left', va='center', family='arial')
            
            # Energy Level:
            ener_text = "Energized"
            de_ener_text = "De-Energized"
            neutral_text = "Neutral"
            energy_level_text = "error"

            if((self_total - con_total) < 0):
                energy_level_text = ener_text
            elif((self_total - con_total) > 0):
                energy_level_text = de_ener_text
            else:
                energy_level_text = neutral_text

            meta_ax.text(1.19, 0.01, f"{energy_level_text}",
                         color='lightgrey', bbox=dict(facecolor=dolphin_blue, edgecolor='gray', boxstyle="round,pad=0.2"),
                         fontsize=18.5, ha='right', va='center', family='monospace')

            # Write graphs
            # ax1 = fig.add_subplot(gs[0, 1])
            # ax2 = fig.add_subplot(gs[1, 1])

            # Custom labels for each dot (A, B, C, D)
            # Average is proj[4]
            self_labels = ['A' + char_decider(self_proj[0], self_proj[4]),
                           'B' + char_decider(self_proj[1], self_proj[4]),
                           'C' + char_decider(self_proj[2], self_proj[4]),
                           'D' + char_decider(self_proj[3], self_proj[4])]
            concept_labels = ['A' + char_decider(concept_proj[0], concept_proj[4]),
                           'B' + char_decider(concept_proj[1], concept_proj[4]),
                           'C' + char_decider(concept_proj[2], concept_proj[4]),
                           'D' + char_decider(concept_proj[3], concept_proj[4])]

            gs = gridspec.GridSpec(
                4, 2, 
                width_ratios=[0.325, 9.2],
                height_ratios=[2.0, 0.8, 1.7, 0]  # control vertical space
            )

            meta_ax = fig.add_subplot(gs[:, 0])
            meta_ax.axis("off")

            ax1 = fig.add_subplot(gs[0, 1])
            gap_ax = fig.add_subplot(gs[1, 1])
            gap_ax.axis("off")
            ax2 = fig.add_subplot(gs[2, 1])

            plot_projection(ax1, self_proj, "", dot_labels=self_labels, decision_value=decision_value)
            if repeat is False:
                ax1.set_title("Self Wiring", fontsize=14, fontweight='bold', pad=20)
            else:
                ax1.set_title("ORIGINAL SELF", fontsize=14, fontweight='bold', pad=20)


            plot_projection(ax2, concept_proj, "", dot_labels=concept_labels)
            if repeat is False:
                ax2.set_title("Adapted Self Wiring", fontsize=14, fontweight='bold', pad=20)
            else:
                ax2.set_title("New Adjusted Self", fontsize=14, fontweight='bold', pad=20)

            fig.suptitle(f"Projection Summary for {name}", fontsize=16, fontweight='bold')
            plt.subplots_adjust(left=0.114, right=0.794, top=0.817, bottom=0.06)
            plt.savefig(f"test_output/{name}_graph.png", dpi=300, bbox_inches='tight')
            plt.show()




        else:
            print("No valid projection data found.")
    else:
        print(f"File {file_path} does not exist.")

if __name__ == "__main__":
    main()
