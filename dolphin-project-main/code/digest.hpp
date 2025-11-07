#include <iostream>
#include <vector>
#include <string>
#include <iostream>
#include <iomanip>
#include <fstream>
#include <unordered_map>
#include <set>
// #include <windows.h>
// #include <unistd.h> // for readlink
// #include <limits.h> // for PATH_MAX
// #include <libgen.h> // for dirname

struct weight_limit {
    int a_self_max, b_self_max, c_self_max, d_self_max;
    int a_con_max, b_con_max, c_con_max, d_con_max;
    int a_adj_max, b_adj_max, c_adj_max, d_adj_max;
    int a_task_max, b_task_max, c_task_max, d_task_max;

    weight_limit() :
        a_self_max(0), b_self_max(0), c_self_max(0), d_self_max(0),
        a_con_max(0), b_con_max(0), c_con_max(0), d_con_max(0),
        a_adj_max(0), b_adj_max(0), c_adj_max(0), d_adj_max(0),
        a_task_max(0), b_task_max(0), c_task_max(0), d_task_max(0)
    {}
};

struct word_info_struct {
    std::unordered_map<std::string, std::pair<char, int> > selfTable;
    std::unordered_map<std::string, std::pair<char, int> > concTable;
    std::unordered_map<std::string, std::pair<char, int> > adjustTable;
    weight_limit ceiling;
};

class personality {
public:
    int aSelf, bSelf, cSelf, dSelf;
    int aCon, bCon, cCon, dCon;
    int aAdj, bAdj, cAdj, dAdj;

    int aSelfCount, bSelfCount, cSelfCount, dSelfCount;
    int aConCount, bConCount, cConCount, dConCount;
    int aAdjCount, bAdjCount, cAdjCount, dAdjCount;
    int aTask, bTask, cTask, dTask;
    std::string name;
    // Self words:
    std::set<std::string> aSWords, bSWords, cSWords, dSWords;
    // Concept Words:
    std::set<std::string> aCWords, bCWords, cCWords, dCWords;
    // Adjusted Words:
    std::set<std::string> aAdjWords, bAdjWords, cAdjWords, dAdjWords;

    // Repeat Information
    std::vector<std::string> RI;
    int old_self_count = 0;

    void clear() {
        aSelf = bSelf = cSelf = dSelf = 0;
        aCon = bCon = cCon = dCon = 0;
        aAdj = bAdj = cAdj = dAdj = 0;
        aTask = bTask = cTask = dTask = 0;
        aSelfCount = bSelfCount = cSelfCount = dSelfCount = 0;
        aConCount = bConCount = cConCount = dConCount = 0;
        aAdjCount = bAdjCount = cAdjCount = dAdjCount = 0;
        name.clear();
        aSWords.clear();
        bSWords.clear();
        cSWords.clear();
        dSWords.clear();
        aCWords.clear();
        bCWords.clear();
        cCWords.clear();
        dCWords.clear();
        aAdjWords.clear();
        bAdjWords.clear();
        cAdjWords.clear();
        dAdjWords.clear();
    }
};


std::string sanitize(std::string);
void set_task_max(weight_limit&);
void digestConc(word_info_struct&, std::string, std::ofstream&);
void digestSelf(word_info_struct&, std::string, std::ofstream&);
std::string handle_argument(int, char **);

std::string sanitize(std::string word) {
    for (int i = 0, len = int(word.size()); i < len; i++)
    {
        // check whether parsing character is punctuation or not
        if (ispunct(word[i])) {word.erase(i--, 1); len = int(word.size());}
        else {word[i] = tolower(word[i]);}
    }
    return word;
}

std::string handle_argument(int argc, char **argv) {
    std::string retakeFile = "";
    
    for (int i = 1; i < argc; ++i) {
        std::string arg(argv[i]);

        if (arg == "-h" || arg == "-help") return "help";

        if (arg == "-r") {
            if (i + 1 < argc) {
                retakeFile = argv[i + 1];
            } else {
                return "No output test";
            }
        }
    }

    return retakeFile;
}

void set_task_max(weight_limit &w) {
    w.a_task_max = w.a_self_max - w.a_adj_max;
    w.b_task_max = w.b_self_max - w.b_adj_max;
    w.c_task_max = w.c_self_max - w.c_adj_max;
    w.d_task_max = w.d_self_max - w.d_adj_max;
}

void digestConc(word_info_struct& word_info, std::string fname, std::ofstream& logOut) {
    std::fstream file;
    logOut << "Opening Concept Words...\n";
    std::string line, word;
    std::pair<char, int> pair;
    int weight = 0;
    file.open(fname);
    int Ind = 0;
   
    if (!file.is_open()) {
        logOut << "ERROR: Failed to open Concept Words with file path: " << fname << "\n\n";
        return;
    }

    logOut << "Successfully opened Concept Words file.\n";
    while (getline(file, line)) {
        if(line[0] == '/') continue;
        Ind++;
        word = line.substr(0, line.find(','));
        word = sanitize(word);
        pair.first = line[line.find(',') + 1];  //Setting the group
        weight = stof(line.substr(line.find(" ") + 1, line.length()));
        pair.second = weight;
        
        //Check duplicate
        if(word_info.concTable.count(word)) {
            logOut << "\n*************************************************\n";
            logOut << "  Duplicate word (" << word << ") found on line " << Ind;
            logOut << "\n             Please fix and run again";
            logOut << "\n*************************************************\n";
            exit(7);
        }else{
            word_info.concTable.insert(std::make_pair(word, pair));
        }
        
        switch (pair.first) {
            case 'A':
                word_info.ceiling.a_con_max += weight;
                break;
            case 'B':
                word_info.ceiling.b_con_max += weight;
                break;
            case 'C':
                word_info.ceiling.c_con_max += weight;
                break;
            case 'D':
                word_info.ceiling.d_con_max += weight;
                break;
                
            default:
                break;
        }
    }
    file.close();
    logOut << "Finished digesting and closed Concept Words file.\n\n";
};

void digestSelf(word_info_struct& word_info, std::string fname, std::ofstream& logOut) {
    std::fstream file;
    logOut << "Opening Self Words...\n";
    std::string line, word;
    std::pair<char, int> pair;
    int weight = 0;
    file.open(fname);
    int Ind = 0;

    if (!file.is_open()) {
        logOut << "ERROR: Failed to open Self Words with file path: " << fname << "\n\n";
        return;
    }
    
    logOut << "Successfully opened Self Words file.\n";
    while (getline(file, line)) {
        if(line[0] == '/') continue;
        Ind++;
        word = line.substr(0, line.find(','));
        word = sanitize(word);
        pair.first = line[line.find(',') + 1];  //Setting the group
        weight = stof(line.substr(line.find(" ") + 1, line.length()));
        pair.second = weight;
        
        //Check duplicate
        if(word_info.selfTable.count(word)) {
            logOut << "\n*************************************************\n";
            logOut << "  Duplicate word (" << word << ") found on line " << Ind;
            logOut << "\n             Please fix and run again";
            logOut << "\n*************************************************\n";
            exit(7);
        }else{
            word_info.selfTable.insert(std::make_pair(word, pair));
        }
        
        switch (pair.first) {
            case 'A':
                word_info.ceiling.a_self_max += weight;
                break;
            case 'B':
                word_info.ceiling.b_self_max += weight;
                break;
            case 'C':
                word_info.ceiling.c_self_max += weight;
                break;
            case 'D':
                word_info.ceiling.d_self_max += weight;
                break;
                
            default:
                break;
        }
    }
    file.close();
    logOut << "Finished digesting and closed Self Words file.\n\n";
};

void digestAdjust(word_info_struct& word_info, std::string fname, std::ofstream& logOut) {
    std::fstream file;
    logOut << "Opening Adjusted Words...\n";
    std::string line, word;
    std::pair<char, int> pair;
    int weight = 0;
    file.open(fname);
    int Ind = 0;
    
    if (!file.is_open()) {
        logOut << "ERROR: Failed to open Adjusted Words with file path: " << fname << "\n\n";
        return;
    }
    
    logOut << "Successfully opened Adjusted Words file.\n";
    while (getline(file, line)) {
        if(line[0] == '/') continue;
        Ind++;
        word = line.substr(0, line.find(','));
        word = sanitize(word);
        pair.first = line[line.find(',') + 1];  //Setting the group
        weight = stof(line.substr(line.find(" ") + 1, line.length()));
        pair.second = weight;
        
        //Check duplicate
        if(word_info.adjustTable.count(word)) {
            logOut << "\n*************************************************\n";
            logOut << "  Duplicate word (" << word << ") found on line " << Ind;
            logOut << "\n             Please fix and run again";
            logOut << "\n*************************************************\n";
            exit(7);
        }else{
            word_info.adjustTable.insert(std::make_pair(word, pair));
        }
        
        switch (pair.first) {
            case 'A':
                word_info.ceiling.a_adj_max += weight;
                break;
            case 'B':
                word_info.ceiling.b_adj_max += weight;
                break;
            case 'C':
                word_info.ceiling.c_adj_max += weight;
                break;
            case 'D':
                word_info.ceiling.d_adj_max += weight;
                break;
                
            default:
                break;
        }
    }
    file.close();
    logOut << "Finished digesting and closed Adjusted Words file.\n\n";
};
